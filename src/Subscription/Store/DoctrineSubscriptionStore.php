<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Schema\DoctrineHelper;
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
use function serialize;
use function sprintf;
use function unserialize;

use const JSON_THROW_ON_ERROR;

/** @phpstan-type Data = array{
 *     id: string,
 *     group_name: string,
 *     run_mode: string,
 *     position: int|null,
 *     status: string,
 *     error_message: string|null,
 *     error_previous_status: string|null,
 *     error_context: string|null,
 *     retry_attempt: int,
 *     last_saved_at: string,
 *     cleanup_tasks: string|null,
 *     subscriber_id: string,
 *     partition_key: string|null,
 * }
 */
final class DoctrineSubscriptionStore implements SubscriptionStore, DoctrineSchemaConfigurator
{
    private readonly string $inboxTableName;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly string $tableName = 'subscriptions',
        string|null $inboxTableName = null,
    ) {
        $this->inboxTableName = $inboxTableName ?? $tableName . '_inbox';
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

    /**
     * With pendingPartitionsOnly, the main subscriptions come first, then the partitions with messages in their inbox.
     *
     * @return list<Subscription>
     */
    public function find(SubscriptionCriteria|null $criteria = null): array
    {
        if ($criteria === null || !$criteria->pendingPartitionsOnly) {
            return $this->fetch($criteria);
        }

        $mains = $criteria->partitions !== true ? $this->fetch($criteria, false) : [];
        $partitions = $criteria->partitions !== false ? $this->fetch($criteria, true) : [];

        return [...$mains, ...$partitions];
    }

    /**
     * Partitions with pending messages are found by joining the inbox, which only holds the unprocessed messages.
     * So the query costs as much as there is work, not as many partitions as there are.
     *
     * @return list<Subscription>
     */
    private function fetch(SubscriptionCriteria|null $criteria, bool|null $pendingPartitions = null): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select($pendingPartitions === true ? $this->tableName . '.*' : '*')
            ->from($this->tableName)
            ->orderBy($pendingPartitions === true ? $this->tableName . '.id' : 'id');

        if ($criteria !== null) {
            $this->applyCriteria($qb, $criteria);
        }

        if ($pendingPartitions === false) {
            $qb->andWhere('partition_key IS NULL');
        }

        if ($pendingPartitions === true) {
            $qb->andWhere('partition_key IS NOT NULL')
                ->innerJoin(
                    $this->tableName,
                    sprintf(
                        '(SELECT subscription_id, MAX(message_index) AS last_index FROM %s GROUP BY subscription_id)',
                        $this->inboxTableName,
                    ),
                    'pending',
                    sprintf(
                        'pending.subscription_id = %1$s.id AND pending.last_index > %1$s.position',
                        $this->tableName,
                    ),
                );
        }

        /** @var list<Data> $result */
        $result = $qb->fetchAllAssociative();

        return array_map(
            fn (array $data) => $this->createSubscription($data),
            $result,
        );
    }

    public function claim(string $id, SubscriptionCriteria $criteria): Subscription|null
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('id = :id')
            ->setParameter('id', $id);

        $this->applyCriteria($qb, $criteria);

        if ($criteria->pendingPartitionsOnly) {
            $qb->andWhere(sprintf(
                '%1$s.partition_key IS NULL OR EXISTS (SELECT 1 FROM %2$s WHERE %2$s.subscription_id = %1$s.id AND %2$s.message_index > %1$s.position)',
                $this->tableName,
                $this->inboxTableName,
            ));
        }

        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $qb->forUpdate(ConflictResolutionMode::SKIP_LOCKED);
        }

        /** @var Data|false $result */
        $result = $qb->fetchAssociative();

        if ($result === false) {
            return null;
        }

        return $this->createSubscription($result);
    }

    private function applyCriteria(QueryBuilder $qb, SubscriptionCriteria $criteria): void
    {
        if ($criteria->ids !== null) {
            $qb->andWhere('id IN (:ids) OR subscriber_id IN (:subscriber_ids)')
                ->setParameter(
                    'ids',
                    $criteria->ids,
                    ArrayParameterType::STRING,
                )
                ->setParameter(
                    'subscriber_ids',
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

        if ($criteria->partitions === null) {
            return;
        }

        $qb->andWhere($criteria->partitions ? 'partition_key IS NOT NULL' : 'partition_key IS NULL');
    }

    public function add(Subscription $subscription): void
    {
        $subscriptionError = $subscription->subscriptionError();

        $subscription->updateLastSavedAt($this->clock->now());

        try {
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
                    'cleanup_tasks' => $subscription->cleanupTasks() !== null ? serialize($subscription->cleanupTasks()) : null,
                    'subscriber_id' => $subscription->subscriberId(),
                    'partition_key' => $subscription->partitionKey(),
                ],
                [
                    'last_saved_at' => Types::DATETIME_IMMUTABLE,
                ],
            );
        } catch (UniqueConstraintViolationException $e) {
            throw new SubscriptionAlreadyExists($subscription->id(), $e);
        }
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
                'cleanup_tasks' => $subscription->cleanupTasks() !== null ? serialize($subscription->cleanupTasks()) : null,
            ],
            [
                'id' => $subscription->id(),
            ],
            [
                'last_saved_at' => Types::DATETIME_IMMUTABLE,
            ],
        );

        if ($effectedRows !== 0) {
            return;
        }

        // mysql and mariadb count the changed rows, not the matched ones.
        // so an update without any change also affects no rows, even if the subscription exists.
        $exists = $this->connection->fetchOne(
            $this->connection->createQueryBuilder()
                ->select('1')
                ->from($this->tableName)
                ->where('id = :id')
                ->getSQL(),
            ['id' => $subscription->id()],
        );

        if ($exists === false) {
            throw new SubscriptionNotFound($subscription->id());
        }
    }

    public function remove(Subscription $subscription): void
    {
        $this->connection->delete($this->tableName, ['id' => $subscription->id()]);
        $this->connection->delete($this->inboxTableName, ['subscription_id' => $subscription->id()]);
    }

    /** @param list<int> $indices */
    public function addToInbox(string $subscriptionId, array $indices): void
    {
        foreach ($indices as $index) {
            $this->connection->insert(
                $this->inboxTableName,
                [
                    'subscription_id' => $subscriptionId,
                    'message_index' => $index,
                ],
            );
        }
    }

    /**
     * @param positive-int $limit
     *
     * @return list<int>
     */
    public function inbox(string $subscriptionId, int $afterIndex, int $limit): array
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('message_index')
            ->from($this->inboxTableName)
            ->where('subscription_id = :subscription_id')
            ->andWhere('message_index > :after_index')
            ->orderBy('message_index')
            ->setMaxResults($limit)
            ->getSQL();

        /** @var list<int|string> $indices */
        $indices = $this->connection->fetchFirstColumn($sql, [
            'subscription_id' => $subscriptionId,
            'after_index' => $afterIndex,
        ]);

        return array_map(static fn (int|string $index): int => (int)$index, $indices);
    }

    public function removeFromInbox(string $subscriptionId, int $toIndex): void
    {
        $this->connection->executeStatement(
            sprintf(
                'DELETE FROM %s WHERE subscription_id = :subscription_id AND message_index <= :to_index',
                $this->inboxTableName,
            ),
            [
                'subscription_id' => $subscriptionId,
                'to_index' => $toIndex,
            ],
        );
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
        } catch (TransactionCommitNotPossible $e) {
            throw $e;
        } catch (RetryableException $e) {
            throw new TransactionCommitNotPossible($e);
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
        if (!DoctrineHelper::sameDatabase($this->connection, $connection)) {
            return;
        }

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
            ->setNotnull(false);
        $table->addColumn('status', Types::STRING)
            ->setLength(32)
            ->setNotnull(true);
        $table->addColumn('error_message', Types::TEXT)
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
        $table->addColumn('cleanup_tasks', Types::TEXT)
            ->setNotnull(false);

        $table->addColumn('subscriber_id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('partition_key', Types::STRING)
            ->setLength(255)
            ->setNotnull(false);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['group_name']);
        $table->addIndex(['status']);
        $table->addIndex(['subscriber_id']);

        $inbox = $schema->createTable($this->inboxTableName);

        $inbox->addColumn('subscription_id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $inbox->addColumn('message_index', Types::INTEGER)
            ->setNotnull(true);

        $inbox->setPrimaryKey(['subscription_id', 'message_index']);
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
            $row['cleanup_tasks'] !== null ? unserialize($row['cleanup_tasks']) : null,
            $row['partition_key'],
        );
    }

    private static function normalizeDateTime(mixed $value, AbstractPlatform $platform): DateTimeImmutable
    {
        $normalizedValue = Type::getType(Types::DATETIMETZ_IMMUTABLE)->convertToPHPValue($value, $platform);

        assert($normalizedValue instanceof DateTimeImmutable);

        return $normalizedValue;
    }
}
