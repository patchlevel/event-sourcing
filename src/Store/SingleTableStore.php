<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use Webmozart\Assert\Assert;
use function array_key_exists;
use function array_map;

final class SingleTableStore extends DoctrineStore
{
    /** @var array<class-string<AggregateRoot>, string> */
    private array $aggregates;
    private string $tableName;

    /**
     * @param array<class-string<AggregateRoot>, string> $aggregates
     */
    public function __construct(Connection $connection, array $aggregates)
    {
        parent::__construct($connection);

        $this->aggregates = $aggregates;
        $this->tableName = 'eventstore';
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return AggregateChanged[]
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = -1): array
    {
        $shortName = $this->shortName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('aggregate = :aggregate AND aggregateId = :id AND playhead > :playhead')
            ->getSQL();

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
        /** @param array<string, mixed> $data */
            static function (array $data) use ($platform) {
                return AggregateChanged::deserialize(
                    self::normalizeResult($platform, $data)
                );
            },
            $result
        );
    }

    /**
     * @return Generator<AggregateChanged>
     */
    public function loadAll(): Generator
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->getSQL();

        $result = $this->connection->executeQuery($sql, []);
        $platform = $this->connection->getDatabasePlatform();

        /** @var array<string, mixed> $data */
        foreach ($result->iterateAssociative() as $data) {
            yield AggregateChanged::deserialize(
                self::normalizeResult($platform, $data)
            );
        }
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function has(string $aggregate, string $id): bool
    {
        $shortName = $this->shortName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->tableName)
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

    public function count(): int
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->tableName)
            ->getSQL();

        return (int)$this->connection->fetchOne($sql);
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     * @param AggregateChanged[]          $events
     */
    public function saveBatch(string $aggregate, string $id, array $events): void
    {
        $shortName = $this->shortName($aggregate);
        $tableName = $this->tableName;

        $this->connection->transactional(
            static function (Connection $connection) use ($shortName, $id, $events, $tableName): void {
                foreach ($events as $event) {
                    if ($event->aggregateId() !== $id) {
                        throw new StoreException('id missmatch');
                    }

                    $data = $event->serialize();
                    $data['aggregate'] = $shortName;

                    $connection->insert(
                        $tableName,
                        $data,
                        [
                            'recordedOn' => Types::DATETIMETZ_IMMUTABLE,
                        ]
                    );
                }
            }
        );
    }

    public function schema(): Schema
    {
        $schema = new Schema([], [], $this->connection->getSchemaManager()->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }

    public function changeTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->tableName);

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
