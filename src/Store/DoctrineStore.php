<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;

use function array_map;
use function is_array;
use function is_int;
use function is_string;

abstract class DoctrineStore implements Store, TransactionStore, OutboxStore, SplitEventstreamStore
{
    private const OUTBOX_TABLE = 'outbox';

    public function __construct(
        protected Connection $connection,
        protected EventSerializer $serializer,
        protected AggregateRootRegistry $aggregateRootRegistry
    ) {
    }

    public function transactionBegin(): void
    {
        $this->connection->beginTransaction();
    }

    public function transactionCommit(): void
    {
        $this->connection->commit();
    }

    public function transactionRollback(): void
    {
        $this->connection->rollBack();
    }

    /**
     * @param Closure():ClosureReturn $function
     *
     * @template ClosureReturn
     */
    public function transactional(Closure $function): void
    {
        $this->connection->transactional($function);
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function saveOutboxMessage(Message ...$messages): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($messages): void {
                foreach ($messages as $message) {
                    $event = $message->event();

                    $data = $this->serializer->serialize($event);

                    $connection->insert(
                        self::OUTBOX_TABLE,
                        [
                            'aggregate' => $this->aggregateRootRegistry->aggregateName($message->aggregateClass()),
                            'aggregate_id' => $message->aggregateId(),
                            'playhead' => $message->playhead(),
                            'event' => $data->name,
                            'payload' => $data->payload,
                            'recorded_on' => $message->recordedOn(),
                            'custom_headers' => $message->customHeaders(),
                        ],
                        [
                            'recorded_on' => Types::DATETIMETZ_IMMUTABLE,
                            'custom_headers' => Types::JSON,
                        ]
                    );
                }
            }
        );
    }

    /**
     * @return list<Message>
     */
    public function retrieveOutboxMessages(?int $limit = null): array
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::OUTBOX_TABLE)
            ->setMaxResults($limit)
            ->getSQL();

        /** @var list<array{aggregate: string, aggregate_id: string, playhead: string|int, event: string, payload: string, recorded_on: string, custom_headers: string}> $result */
        $result = $this->connection->fetchAllAssociative($sql);
        $platform = $this->connection->getDatabasePlatform();

        return array_map(
            function (array $data) use ($platform) {
                $event = $this->serializer->deserialize(new SerializedEvent($data['event'], $data['payload']));

                return Message::create($event)
                    ->withAggregateClass($this->aggregateRootRegistry->aggregateClass($data['aggregate']))
                    ->withAggregateId($data['aggregate_id'])
                    ->withPlayhead(self::normalizePlayhead($data['playhead'], $platform))
                    ->withRecordedOn(self::normalizeRecordedOn($data['recorded_on'], $platform))
                    ->withCustomHeaders(self::normalizeCustomHeaders($data['custom_headers'], $platform));
            },
            $result
        );
    }

    public function markOutboxMessageConsumed(Message ...$messages): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($messages): void {
                foreach ($messages as $message) {
                    $connection->delete(
                        self::OUTBOX_TABLE,
                        [
                            'aggregate' => $this->aggregateRootRegistry->aggregateName($message->aggregateClass()),
                            'aggregate_id' => $message->aggregateId(),
                            'playhead' => $message->playhead(),
                        ]
                    );
                }
            }
        );
    }

    public function countOutboxMessages(): int
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(self::OUTBOX_TABLE)
            ->getSQL();

        $result = $this->connection->fetchOne($sql);

        if (!is_int($result) && !is_string($result)) {
            throw new WrongQueryResult();
        }

        return (int)$result;
    }

    /**
     * @deprecated use DoctrineSchemaDirector
     */
    public function schema(): Schema
    {
        $schema = new Schema([], [], $this->connection->createSchemaManager()->createSchemaConfig());

        if ($this instanceof SchemaConfigurator) {
            $this->configureSchema($schema, $this->connection);
        }

        return $schema;
    }

    protected static function normalizeRecordedOn(string $recordedOn, AbstractPlatform $platform): DateTimeImmutable
    {
        $normalizedRecordedOn = Type::getType(Types::DATETIMETZ_IMMUTABLE)->convertToPHPValue($recordedOn, $platform);

        if (!$normalizedRecordedOn instanceof DateTimeImmutable) {
            throw new InvalidType('recorded_on', DateTimeImmutable::class);
        }

        return $normalizedRecordedOn;
    }

    /**
     * @return positive-int
     */
    protected static function normalizePlayhead(string|int $playhead, AbstractPlatform $platform): int
    {
        $normalizedPlayhead = Type::getType(Types::INTEGER)->convertToPHPValue($playhead, $platform);

        if (!is_int($normalizedPlayhead) || $normalizedPlayhead <= 0) {
            throw new InvalidType('playhead', 'positive-int');
        }

        return $normalizedPlayhead;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function normalizeCustomHeaders(string $customHeaders, AbstractPlatform $platform): array
    {
        $normalizedCustomHeaders = Type::getType(Types::JSON)->convertToPHPValue($customHeaders, $platform);

        if (!is_array($normalizedCustomHeaders)) {
            throw new InvalidType('custom_headers', 'array');
        }

        return $normalizedCustomHeaders;
    }

    protected function addOutboxSchema(Schema $schema): void
    {
        $table = $schema->createTable('outbox');

        $table->addColumn('aggregate', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('aggregate_id', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('event', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recorded_on', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(false);
        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->setPrimaryKey(['aggregate', 'aggregate_id', 'playhead']);
    }
}
