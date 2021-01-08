<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use RuntimeException;

use function array_map;
use function array_pop;
use function explode;
use function sprintf;

final class SingleTableStore implements Store
{
    private const TABLE_NAME = 'eventstore';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return AggregateChanged[]
     */
    public function load(string $aggregate, string $id): array
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('aggregate = :aggregate AND aggregateId = :id')
            ->getSQL();

        $result = $this->connection->fetchAllAssociative(
            $sql,
            [
                'aggregate' => self::shortName($aggregate),
                'id' => $id,
            ],
            [
                'recordedOn' => Types::DATETIMETZ_IMMUTABLE,
            ]
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
     * @return Generator<AggregateChanged>
     */
    public function loadAll(): Generator
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_NAME)
            ->getSQL();

        $result = $this->connection->executeQuery(
            $sql,
            [],
            [
                'recordedOn' => Types::DATETIMETZ_IMMUTABLE,
            ]
        );

        /** @var array<string, mixed> $data */
        foreach ($result->iterateAssociative() as $data) {
            yield AggregateChanged::deserialize($data);
        }
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function has(string $aggregate, string $id): bool
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(self::TABLE_NAME)
            ->where('aggregate = :aggregate AND aggregateId = :id')
            ->setMaxResults(1)
            ->getSQL();

        $result = (int)$this->connection->fetchOne(
            $sql,
            [
                'aggregate' => self::shortName($aggregate),
                'id' => $id,
            ]
        );

        return $result > 0;
    }

    public function count(): int
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(self::TABLE_NAME)
            ->getSQL();

        return (int)$this->connection->fetchOne($sql);
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     * @param AggregateChanged[]          $events
     */
    public function saveBatch(string $aggregate, string $id, array $events): void
    {
        $this->connection->transactional(
            static function (Connection $connection) use ($aggregate, $id, $events): void {
                foreach ($events as $event) {
                    if ($event->aggregateId() !== $id) {
                        throw new StoreException('id missmatch');
                    }

                    $data = $event->serialize();
                    $data['aggregate'] = self::shortName($aggregate);

                    $connection->insert(
                        self::TABLE_NAME,
                        $data,
                        [
                            'recordedOn' => Types::DATETIMETZ_IMMUTABLE,
                        ]
                    );
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
        $this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s;', self::TABLE_NAME));
    }

    private function schema(): Schema
    {
        $schema = new Schema([], [], $this->connection->getSchemaManager()->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable(self::TABLE_NAME);

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
     * @param class-string<AggregateRoot> $name
     */
    private static function shortName(string $name): string
    {
        $parts = explode('\\', $name);
        $shortName = array_pop($parts);

        if (!$shortName) {
            throw new RuntimeException(sprintf('%s is not a valid classname', $name));
        }

        return $shortName;
    }
}
