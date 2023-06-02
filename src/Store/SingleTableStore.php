<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;

use function array_map;
use function is_int;
use function is_string;
use function sprintf;

final class SingleTableStore implements StreamableStore, SchemaConfigurator, Store, ArchivableStore
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventSerializer $serializer,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly string $storeTableName = 'eventstore',
    ) {
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return list<Message>
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = 0): array
    {
        $shortName = $this->aggregateRootRegistry->aggregateName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->storeTableName)
            ->where('aggregate = :aggregate')
            ->andWhere('aggregate_id = :id')
            ->andWhere('playhead > :playhead')
            ->andWhere('archived = :archived')
            ->getSQL();

        /** @var list<array{aggregate_id: string, playhead: string|int, event: string, payload: string, recorded_on: string, custom_headers: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            $sql,
            [
                'aggregate' => $shortName,
                'id' => $id,
                'playhead' => $fromPlayhead,
                'archived' => false,
            ],
            [
                'archived' => Types::BOOLEAN,
            ],
        );

        $platform = $this->connection->getDatabasePlatform();

        return array_map(
            function (array $data) use ($platform, $aggregate) {
                $event = $this->serializer->deserialize(new SerializedEvent($data['event'], $data['payload']));

                return Message::create($event)
                    ->withAggregateClass($aggregate)
                    ->withAggregateId($data['aggregate_id'])
                    ->withPlayhead(DoctrineHelper::normalizePlayhead($data['playhead'], $platform))
                    ->withRecordedOn(DoctrineHelper::normalizeRecordedOn($data['recorded_on'], $platform))
                    ->withCustomHeaders(DoctrineHelper::normalizeCustomHeaders($data['custom_headers'], $platform));
            },
            $result,
        );
    }

    /** @param class-string<AggregateRoot> $aggregate */
    public function archiveMessages(string $aggregate, string $id, int $untilPlayhead): void
    {
        $aggregateName = $this->aggregateRootRegistry->aggregateName($aggregate);

        $statement = $this->connection->prepare(sprintf(
            'UPDATE %s 
            SET archived = true
            WHERE aggregate = :aggregate
            AND aggregate_id = :aggregate_id
            AND playhead < :playhead
            AND archived = false',
            $this->storeTableName,
        ));
        $statement->bindValue('aggregate', $aggregateName);
        $statement->bindValue('aggregate_id', $id);
        $statement->bindValue('playhead', $untilPlayhead);

        $statement->executeQuery();
    }

    /** @param class-string<AggregateRoot> $aggregate */
    public function has(string $aggregate, string $id): bool
    {
        $shortName = $this->aggregateRootRegistry->aggregateName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->storeTableName)
            ->where('aggregate = :aggregate')
            ->andWhere('aggregate_id = :id')
            ->setMaxResults(1)
            ->getSQL();

        $result = $this->connection->fetchOne(
            $sql,
            [
                'aggregate' => $shortName,
                'id' => $id,
            ],
        );

        if (!is_int($result) && !is_string($result)) {
            throw new WrongQueryResult();
        }

        return ((int)$result) > 0;
    }

    public function save(Message ...$messages): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($messages): void {
                foreach ($messages as $message) {
                    $data = $this->serializer->serialize($message->event());

                    $connection->insert(
                        $this->storeTableName,
                        [
                            'aggregate' => $this->aggregateRootRegistry->aggregateName($message->aggregateClass()),
                            'aggregate_id' => $message->aggregateId(),
                            'playhead' => $message->playhead(),
                            'event' => $data->name,
                            'payload' => $data->payload,
                            'recorded_on' => $message->recordedOn(),
                            'new_stream_start' => $message->newStreamStart(),
                            'archived' => $message->archived(),
                            'custom_headers' => $message->customHeaders(),
                        ],
                        [
                            'recorded_on' => Types::DATETIMETZ_IMMUTABLE,
                            'custom_headers' => Types::JSON,
                            'new_stream_start' => Types::BOOLEAN,
                            'archived' => Types::BOOLEAN,
                        ],
                    );
                }
            },
        );
    }

    /** @return Generator<Message> */
    public function stream(int $fromIndex = 0): Generator
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->storeTableName)
            ->where('id > :index')
            ->orderBy('id')
            ->getSQL();

        /**
         * @var array<array{
         *     id: string,
         *     aggregate_id: string,
         *     aggregate: string,
         *     playhead: string,
         *     event: string,
         *     payload: string,
         *     recorded_on: string,
         *     custom_headers: string
         * }> $result
         */
        $result = $this->connection->iterateAssociative($sql, ['index' => $fromIndex]);
        $platform = $this->connection->getDatabasePlatform();

        foreach ($result as $data) {
            $event = $this->serializer->deserialize(new SerializedEvent($data['event'], $data['payload']));

            yield Message::create($event)
                ->withAggregateClass($this->aggregateRootRegistry->aggregateClass($data['aggregate']))
                ->withAggregateId($data['aggregate_id'])
                ->withPlayhead(DoctrineHelper::normalizePlayhead($data['playhead'], $platform))
                ->withRecordedOn(DoctrineHelper::normalizeRecordedOn($data['recorded_on'], $platform))
                ->withCustomHeaders(DoctrineHelper::normalizeCustomHeaders($data['custom_headers'], $platform));
        }
    }

    public function count(int $fromIndex = 0): int
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->storeTableName)
            ->where('id > :index')
            ->getSQL();

        $result = $this->connection->fetchOne($sql, ['index' => $fromIndex]);

        if (!is_int($result) && !is_string($result)) {
            throw new WrongQueryResult();
        }

        return (int)$result;
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

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        if ($this->connection !== $connection) {
            return;
        }

        $table = $schema->createTable($this->storeTableName);

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
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
        $table->addColumn('new_stream_start', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('archived', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate', 'aggregate_id', 'playhead']);
        $table->addIndex(['aggregate', 'aggregate_id', 'playhead', 'archived']);
    }
}
