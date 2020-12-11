<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use RuntimeException;
use function sprintf;

final class MysqlMultiTableStore implements Store
{
    private Connection $connection;

    /**
     * @var array<class-string>
     */
    private array $aggregates;

    /**
     * @param array<class-string> $aggregates
     */
    public function __construct(Connection $eventConnection, array $aggregates)
    {
        $this->connection = $eventConnection;
        $this->aggregates = $aggregates;
    }

    /**
     * @param class-string $aggregate
     *
     * @return AggregateChanged[]
     */
    public function load(string $aggregate, string $id): array
    {
        $tableName = self::tableName($aggregate);

        $result = $this->connection->fetchAllAssociative(
            "
                SELECT * 
                FROM $tableName 
                WHERE aggregateId = :id
            ",
            [
                'id' => $id,
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
     * @param class-string $aggregate
     */
    public function has(string $aggregate, string $id): bool
    {
        $tableName = self::tableName($aggregate);

        $result = (int)$this->connection->fetchOne(
            "
                SELECT COUNT(*) 
                FROM $tableName 
                WHERE aggregateId = :id
                LIMIT 1
            ",
            [
                'id' => $id,
            ]
        );

        return $result > 0;
    }

    /**
     * @param class-string $aggregate
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
        foreach ($this->aggregates as $aggregate) {
            $this->createTableForAggregate($aggregate);
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
    public function createTableForAggregate(string $aggregate): void
    {
        $tableName = self::tableName($aggregate);

        $this->connection->executeQuery("
            CREATE TABLE IF NOT EXISTS $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aggregateId VARCHAR(255) NOT NULL,
                playhead INT NOT NULL,
                event VARCHAR(255) NOT NULL,
                payload JSON NOT NULL,
                recordedOn DATETIME NOT NULL,
                UNIQUE KEY aggregate_key (aggregateId, playhead)
            )  
        ");
    }

    /**
     * @param class-string $aggregate
     */
    public function dropTableForAggregate(string $aggregate): void
    {
        $tableName = self::tableName($aggregate);

        $this->connection->executeQuery("DROP TABLE IF EXISTS $tableName;");
    }

    /**
     * @param class-string $name
     */
    private static function tableName(string $name): string
    {
        $parts = explode('\\', $name);
        $shortName = array_pop($parts);

        if (!$shortName) {
            throw new RuntimeException(sprintf('%s is not a valid classname', $name));
        }

        $string = (string)preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $shortName);

        if (!$string) {
            throw new RuntimeException(sprintf('%s is not a valid table name', $string));
        }

        return strtolower($string);
    }
}
