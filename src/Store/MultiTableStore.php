<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use function array_map;
use function array_pop;
use function explode;
use function preg_replace;
use function sprintf;
use function strtolower;

final class MultiTableStore implements Store
{
    private Connection $connection;

    /** @var list<class-string<AggregateRoot>> */
    private array $aggregates;

    /**
     * @param list<class-string<AggregateRoot>> $aggregates
     */
    public function __construct(Connection $eventConnection, array $aggregates)
    {
        $this->connection = $eventConnection;
        $this->aggregates = $aggregates;
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return AggregateChanged[]
     */
    public function load(string $aggregate, string $id): array
    {
        $tableName = self::tableName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($tableName)
            ->where('aggregateId = :id')
            ->getSQL();

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['id' => $id]
        );

        return array_map(
        /** @param array<string, mixed> $data */
            static function (array $data) {
                return AggregateChanged::deserialize($data);
            },
            $result
        );
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function has(string $aggregate, string $id): bool
    {
        $tableName = self::tableName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($tableName)
            ->where('aggregateId = :id')
            ->setMaxResults(1)
            ->getSQL();

        $result = (int)$this->connection->fetchOne(
            $sql,
            ['id' => $id]
        );

        return $result > 0;
    }

    /**
     * @param class-string       $aggregate
     * @param AggregateChanged[] $events
     */
    public function saveBatch(string $aggregate, string $id, array $events): void
    {
        $tableName = self::tableName($aggregate);

        $this->connection->transactional(
            static function (Connection $connection) use ($tableName, $id, $events): void {
                foreach ($events as $event) {
                    if ($event->aggregateId() !== $id) {
                        throw new StoreException('id missmatch');
                    }

                    $data = $event->serialize();
                    $connection->insert($tableName, $data);
                }
            }
        );
    }

    public function prepare(): void
    {
        $schemaManager = $this->connection->getSchemaManager();

        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($schemaManager->createSchema(), $this->schema());

        foreach ($schemaDiff->toSaveSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function drop(): void
    {
        foreach ($this->aggregates as $aggregate) {
            $this->dropTableForAggregate($aggregate);
        }
    }

    /**
     * @param class-string $aggregate
     */
    public function dropTableForAggregate(string $aggregate): void
    {
        $tableName = self::tableName($aggregate);

        $this->connection->executeQuery(sprintf('DROP TABLE IF EXISTS %s;', $tableName));
    }

    private function schema(): Schema
    {
        $schema = new Schema([], [], $this->connection->getSchemaManager()->createSchemaConfig());

        foreach ($this->aggregates as $aggregateClass) {
            $this->addTableToSchema($schema, $aggregateClass);
        }

        return $schema;
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    private function addTableToSchema(Schema $schema, $aggregateClass): void
    {
        $tableName = self::tableName($aggregateClass);
        $table = $schema->createTable($tableName);

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('aggregateId', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('event', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recordedOn', Types::DATE_IMMUTABLE)
            ->setNotnull(false);

        $table->setPrimaryKey(['id']);

        $table->addUniqueIndex(['aggregateId', 'playhead']);
    }

    /**
     * @param class-string<AggregateRoot> $name
     */
    private static function tableName(string $name): string
    {
        $parts = explode('\\', $name);
        $shortName = array_pop($parts);

        if (!$shortName) {
            throw new StoreException(sprintf('%s is not a valid classname', $name));
        }

        $string = (string)preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $shortName);

        if (!$string) {
            throw new StoreException(sprintf('%s is not a valid table name', $string));
        }

        return strtolower($string);
    }
}
