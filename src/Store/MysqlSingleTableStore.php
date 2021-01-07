<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Connection;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use RuntimeException;
use function array_pop;
use function explode;
use function sprintf;

final class MysqlSingleTableStore implements Store
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param class-string $aggregate
     *
     * @return AggregateChanged[]
     */
    public function load(string $aggregate, string $id): array
    {
        $result = $this->connection->fetchAllAssociative(
            '
                SELECT * 
                FROM eventstore 
                WHERE aggregate = :aggregate AND aggregateId = :id
            ',
            [
                'aggregate' => self::shortName($aggregate),
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
     * @return Generator<AggregateChanged>
     */
    public function loadAll(): Generator
    {
        $result = $this->connection->executeQuery('SELECT * FROM eventstore');

        /** @var array<string, mixed> $data */
        foreach ($result as $data) {
            yield AggregateChanged::deserialize($data);
        }
    }

    /**
     * @param class-string $aggregate
     */
    public function has(string $aggregate, string $id): bool
    {
        $result = (int)$this->connection->fetchOne(
            '
                SELECT COUNT(*) 
                FROM eventstore 
                WHERE aggregate = :aggregate AND aggregateId = :id
                LIMIT 1
            ',
            [
                'aggregate' => self::shortName($aggregate),
                'id' => $id,
            ]
        );

        return $result > 0;
    }

    public function count(): int
    {
        return (int)$this->connection->fetchOne('SELECT COUNT(*) FROM eventstore');
    }

    /**
     * @param class-string $aggregate
     * @param AggregateChanged[] $events
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

                    $connection->insert('eventstore', $data);
                }
            }
        );
    }

    public function prepare(): void
    {
        $this->connection->executeQuery('
            CREATE TABLE IF NOT EXISTS eventstore (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aggregate VARCHAR(255) NOT NULL,
                aggregateId VARCHAR(255) NOT NULL,
                playhead INT NOT NULL,
                event VARCHAR(255) NOT NULL,
                payload JSON NOT NULL,
                recordedOn DATETIME NOT NULL,
                UNIQUE KEY aggregate_key (aggregate, aggregateId, playhead)
            )  
        ');
    }

    public function drop(): void
    {
        $this->connection->executeQuery('DROP TABLE IF EXISTS eventstore;');
    }

    /**
     * @param class-string $name
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
