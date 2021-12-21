<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Pipeline\EventBucket;

use function array_flip;
use function array_key_exists;
use function array_map;
use function is_int;
use function is_string;

final class SingleTableStore extends DoctrineStore implements PipelineStore
{
    /** @var array<class-string<AggregateRoot>, string> */
    private array $aggregates;
    private string $storeTableName;

    /**
     * @param array<class-string<AggregateRoot>, string> $aggregates
     */
    public function __construct(Connection $connection, array $aggregates, string $storeTableName)
    {
        parent::__construct($connection);

        $this->aggregates = $aggregates;
        $this->storeTableName = $storeTableName;
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return array<AggregateChanged<array<string, mixed>>>
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = 0): array
    {
        $shortName = $this->shortName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->storeTableName)
            ->where('aggregate = :aggregate AND aggregateId = :id AND playhead > :playhead')
            ->getSQL();

        /** @var array<array{aggregateId: string, playhead: string|int, event: class-string<AggregateChanged<array<string, mixed>>>, payload: string, recordedOn: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            $sql,
            [
                'aggregate' => $shortName,
                'id' => $id,
                'playhead' => $fromPlayhead,
            ]
        );

        $platform = $this->connection->getDatabasePlatform();

        return array_map(
            static function (array $data) use ($platform) {
                return AggregateChanged::deserialize(
                    self::normalizeResult($platform, $data)
                );
            },
            $result
        );
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function has(string $aggregate, string $id): bool
    {
        $shortName = $this->shortName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->storeTableName)
            ->where('aggregate = :aggregate AND aggregateId = :id')
            ->setMaxResults(1)
            ->getSQL();

        $result = $this->connection->fetchOne(
            $sql,
            [
                'aggregate' => $shortName,
                'id' => $id,
            ]
        );

        if (!is_int($result) && !is_string($result)) {
            throw new WrongQueryResult();
        }

        return ((int)$result) > 0;
    }

    /**
     * @param class-string<AggregateRoot>                   $aggregate
     * @param array<AggregateChanged<array<string, mixed>>> $events
     */
    public function saveBatch(string $aggregate, string $id, array $events): void
    {
        $shortName = $this->shortName($aggregate);
        $storeTableName = $this->storeTableName;

        $this->connection->transactional(
            static function (Connection $connection) use ($shortName, $id, $events, $storeTableName): void {
                foreach ($events as $event) {
                    if ($event->aggregateId() !== $id) {
                        throw new AggregateIdMismatch($id, $event->aggregateId());
                    }

                    $data = $event->serialize();
                    $data['aggregate'] = $shortName;

                    $connection->insert(
                        $storeTableName,
                        $data,
                        [
                            'recordedOn' => Types::DATETIMETZ_IMMUTABLE,
                        ]
                    );
                }
            }
        );
    }

    /**
     * @return Generator<EventBucket>
     */
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
         *     aggregateId: string,
         *     aggregate: string,
         *     playhead: string,
         *     event: class-string<AggregateChanged<array<string, mixed>>>,
         *     payload: string,
         *     recordedOn: string
         * }> $result
         */
        $result = $this->connection->iterateAssociative($sql, ['index' => $fromIndex]);
        $platform = $this->connection->getDatabasePlatform();

        $classMap = array_flip($this->aggregates);

        foreach ($result as $data) {
            $name = $data['aggregate'];

            if (!array_key_exists($name, $classMap)) {
                throw new AggregateNotDefined($name);
            }

            yield new EventBucket(
                $classMap[$name],
                (int)$data['id'],
                AggregateChanged::deserialize(
                    self::normalizeResult($platform, $data)
                )
            );
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

    public function saveEventBucket(EventBucket $bucket): void
    {
        $data = $bucket->event()->serialize();
        $data['aggregate'] = $this->shortName($bucket->aggregateClass());

        $this->connection->insert(
            $this->storeTableName,
            $data,
            [
                'recordedOn' => Types::DATETIMETZ_IMMUTABLE,
            ]
        );
    }

    public function schema(): Schema
    {
        $schema = new Schema([], [], $this->connection->createSchemaManager()->createSchemaConfig());
        $table = $schema->createTable($this->storeTableName);

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('aggregate', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('aggregateId', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('event', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recordedOn', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(false);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate', 'aggregateId', 'playhead']);

        return $schema;
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    private function shortName(string $aggregate): string
    {
        if (!array_key_exists($aggregate, $this->aggregates)) {
            throw new AggregateNotDefined($aggregate);
        }

        return $this->aggregates[$aggregate];
    }
}
