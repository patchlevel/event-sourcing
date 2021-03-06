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
     * @return array<AggregateChanged>
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = -1): array
    {
        $shortName = $this->shortName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->storeTableName)
            ->where('aggregate = :aggregate AND aggregateId = :id AND playhead > :playhead')
            ->getSQL();

        /** @var array<array{aggregateId: string, playhead: string, event: class-string<AggregateChanged>, payload: string, recordedOn: string}> $result */
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

        $result = (int)$this->connection->fetchOne(
            $sql,
            [
                'aggregate' => $shortName,
                'id' => $id,
            ]
        );

        return $result > 0;
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     * @param array<AggregateChanged>     $events
     */
    public function saveBatch(string $aggregate, string $id, array $events): void
    {
        $shortName = $this->shortName($aggregate);
        $storeTableName = $this->storeTableName;

        $this->connection->transactional(
            static function (Connection $connection) use ($shortName, $id, $events, $storeTableName): void {
                foreach ($events as $event) {
                    if ($event->aggregateId() !== $id) {
                        throw new StoreException('id missmatch');
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
    public function all(): Generator
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->storeTableName)
            ->orderBy('id')
            ->getSQL();

        /** @var array<array{aggregateId: string, aggregate: string, playhead: string, event: class-string<AggregateChanged>, payload: string, recordedOn: string}> $result */
        $result = $this->connection->iterateAssociative($sql);
        $platform = $this->connection->getDatabasePlatform();

        $classMap = array_flip($this->aggregates);

        foreach ($result as $data) {
            $name = $data['aggregate'];

            if (!array_key_exists($name, $classMap)) {
                throw new StoreException();
            }

            yield new EventBucket(
                $classMap[$name],
                AggregateChanged::deserialize(
                    self::normalizeResult($platform, $data)
                )
            );
        }
    }

    public function count(): int
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->storeTableName)
            ->getSQL();

        return (int)$this->connection->fetchOne($sql);
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
        $schema = new Schema([], [], $this->connection->getSchemaManager()->createSchemaConfig());
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
