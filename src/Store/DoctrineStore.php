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
use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStatus;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateCollection;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateNotFound;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStore;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;

use function array_map;
use function is_array;
use function is_int;
use function is_string;

abstract class DoctrineStore implements Store, TransactionStore, OutboxStore, ProjectorStore, SplitEventstreamStore
{
    private const OUTBOX_TABLE = 'outbox';
    private const PROJECTOR_TABLE = 'projector';

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

    public function getProjectorState(ProjectorId $projectorId): ProjectorState
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::PROJECTOR_TABLE)
            ->where('projector = :projector AND version = :version')
            ->setParameters([
                'projector' => $projectorId->name(),
                'version' => $projectorId->version(),
            ])
            ->getSQL();

        /** @var array{projector: string, version: int, position: int, status: string}|false $result */
        $result = $this->connection->fetchOne($sql);

        if ($result === false) {
            throw new ProjectorStateNotFound();
        }

        return new ProjectorState(
            new ProjectorId($result['projector'], $result['version']),
            ProjectorStatus::from($result['status']),
            $result['position']
        );
    }

    public function getStateFromAllProjectors(): ProjectorStateCollection
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::PROJECTOR_TABLE)
            ->getSQL();

        /** @var list<array{projector: string, version: int, position: int, status: string}> $result */
        $result = $this->connection->fetchAllAssociative($sql);

        return new ProjectorStateCollection(
            array_map(
                static function (array $data) {
                    return new ProjectorState(
                        new ProjectorId($data['projector'], $data['version']),
                        ProjectorStatus::from($data['status']),
                        $data['position']
                    );
                },
                $result
            )
        );
    }

    public function saveProjectorState(ProjectorState ...$projectorStates): void
    {
        $this->connection->transactional(
            static function (Connection $connection) use ($projectorStates): void {
                foreach ($projectorStates as $projectorState) {
                    $connection->insert(
                        self::PROJECTOR_TABLE,
                        [
                            'projector' => $projectorState->id()->name(),
                            'version' => $projectorState->id()->version(),
                            'position' => $projectorState->position(),
                            'status' => $projectorState->status(),
                        ]
                    );
                }
            }
        );
    }

    public function removeProjectorState(ProjectorId $projectorId): void
    {
        $this->connection->delete(self::PROJECTOR_TABLE, [
            'projector' => $projectorId->name(),
            'version' => $projectorId->version(),
        ]);
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
        $table = $schema->createTable(self::OUTBOX_TABLE);

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

    protected function addProjectorSchema(Schema $schema): void
    {
        $table = $schema->createTable(self::PROJECTOR_TABLE);

        $table->addColumn('projector', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('version', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('position', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('status', Types::STRING)
            ->setNotnull(true);

        $table->setPrimaryKey(['projector', 'version']);
    }
}
