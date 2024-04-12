<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Psr\Clock\ClockInterface;

use function array_map;
use function assert;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @psalm-type Data = array{
 *     id: string,
 *     group_name: string,
 *     run_mode: string,
 *     position: int,
 *     status: string,
 *     error_message: string|null,
 *     error_previous_status: string|null,
 *     error_context: string|null,
 *     retry_attempt: int,
 *     last_saved_at: string,
 * }
 */
final class DoctrineSubscriptionStore implements LockableSubscriptionStore, DoctrineSchemaConfigurator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly string $tableName = 'subscriptions',
    ) {
    }

    public function get(string $subscriptionId): Subscription
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('id = :id')
            ->getSQL();

        /** @var Data|false $result */
        $result = $this->connection->fetchAssociative($sql, ['id' => $subscriptionId]);

        if ($result === false) {
            throw new SubscriptionNotFound($subscriptionId);
        }

        return $this->createSubscription($result);
    }

    /** @return list<Subscription> */
    public function find(SubscriptionCriteria|null $criteria = null): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->orderBy('id');

        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $qb->forUpdate();
        }

        if ($criteria !== null) {
            if ($criteria->ids !== null) {
                $qb->andWhere('id IN (:ids)')
                    ->setParameter(
                        'ids',
                        $criteria->ids,
                        ArrayParameterType::STRING,
                    );
            }

            if ($criteria->groups !== null) {
                $qb->andWhere('group_name IN (:groups)')
                    ->setParameter(
                        'groups',
                        $criteria->groups,
                        ArrayParameterType::STRING,
                    );
            }

            if ($criteria->status !== null) {
                $qb->andWhere('status IN (:status)')
                    ->setParameter(
                        'status',
                        array_map(static fn (Status $status) => $status->value, $criteria->status),
                        ArrayParameterType::STRING,
                    );
            }
        }

        /** @var list<Data> $result */
        $result = $qb->fetchAllAssociative();

        return array_map(
            fn (array $data) => $this->createSubscription($data),
            $result,
        );
    }

    public function add(Subscription $subscription): void
    {
        $subscriptionError = $subscription->subscriptionError();

        $subscription->updateLastSavedAt($this->clock->now());

        $this->connection->insert(
            $this->tableName,
            [
                'id' => $subscription->id(),
                'group_name' => $subscription->group(),
                'run_mode' => $subscription->runMode()->value,
                'status' => $subscription->status()->value,
                'position' => $subscription->position(),
                'error_message' => $subscriptionError?->errorMessage,
                'error_previous_status' => $subscriptionError?->previousStatus?->value,
                'error_context' => $subscriptionError?->errorContext !== null ? json_encode($subscriptionError->errorContext, JSON_THROW_ON_ERROR) : null,
                'retry_attempt' => $subscription->retryAttempt(),
                'last_saved_at' => $subscription->lastSavedAt(),
            ],
            [
                'last_saved_at' => Types::DATETIME_IMMUTABLE,
            ],
        );
    }

    public function update(Subscription $subscription): void
    {
        $subscriptionError = $subscription->subscriptionError();

        $subscription->updateLastSavedAt($this->clock->now());

        $effectedRows = $this->connection->update(
            $this->tableName,
            [
                'group_name' => $subscription->group(),
                'run_mode' => $subscription->runMode()->value,
                'status' => $subscription->status()->value,
                'position' => $subscription->position(),
                'error_message' => $subscriptionError?->errorMessage,
                'error_previous_status' => $subscriptionError?->previousStatus?->value,
                'error_context' => $subscriptionError?->errorContext !== null ? json_encode($subscriptionError->errorContext, JSON_THROW_ON_ERROR) : null,
                'retry_attempt' => $subscription->retryAttempt(),
                'last_saved_at' => $subscription->lastSavedAt(),
            ],
            [
                'id' => $subscription->id(),
            ],
            [
                'last_saved_at' => Types::DATETIME_IMMUTABLE,
            ],
        );

        if ($effectedRows === 0) {
            throw new SubscriptionNotFound($subscription->id());
        }
    }

    public function remove(Subscription $subscription): void
    {
        $this->connection->delete($this->tableName, ['id' => $subscription->id()]);
    }

    /**
     * @param Closure():T $closure
     *
     * @return T
     *
     * @throws TransactionCommitNotPossible
     *
     * @template T
     */
    public function inLock(Closure $closure): mixed
    {
        $this->connection->beginTransaction();

        try {
            return $closure();
        } finally {
            try {
                $this->connection->commit();
            } catch (DriverException $e) {
                throw new TransactionCommitNotPossible($e);
            }
        }
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        $table = $schema->createTable($this->tableName);

        $table->addColumn('id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('group_name', Types::STRING)
            ->setLength(32)
            ->setNotnull(true);
        $table->addColumn('run_mode', Types::STRING)
            ->setLength(16)
            ->setNotnull(true);
        $table->addColumn('position', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('status', Types::STRING)
            ->setLength(32)
            ->setNotnull(true);
        $table->addColumn('error_message', Types::STRING)
            ->setLength(255)
            ->setNotnull(false);
        $table->addColumn('error_previous_status', Types::STRING)
            ->setLength(32)
            ->setNotnull(false);
        $table->addColumn('error_context', Types::JSON)
            ->setNotnull(false);
        $table->addColumn('retry_attempt', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('last_saved_at', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['group_name']);
        $table->addIndex(['status']);
    }

    /** @param Data $row */
    private function createSubscription(array $row): Subscription
    {
        $context = $row['error_context'] !== null ?
            json_decode($row['error_context'], true, 512, JSON_THROW_ON_ERROR) : null;

        return new Subscription(
            $row['id'],
            $row['group_name'],
            RunMode::from($row['run_mode']),
            Status::from($row['status']),
            $row['position'],
            $row['error_message'] !== null ? new SubscriptionError(
                $row['error_message'],
                $row['error_previous_status'] !== null ? Status::from($row['error_previous_status']) : Status::New,
                $context,
            ) : null,
            $row['retry_attempt'],
            self::normalizeDateTime($row['last_saved_at'], $this->connection->getDatabasePlatform()),
        );
    }

    private static function normalizeDateTime(mixed $value, AbstractPlatform $platform): DateTimeImmutable
    {
        $normalizedValue = Type::getType(Types::DATETIMETZ_IMMUTABLE)->convertToPHPValue($value, $platform);

        assert($normalizedValue instanceof DateTimeImmutable);

        return $normalizedValue;
    }
}
