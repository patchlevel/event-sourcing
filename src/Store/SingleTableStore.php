<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\Message;

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
     * @return list<Message>
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = 0): array
    {
        $shortName = $this->shortName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->storeTableName)
            ->where('aggregate = :aggregate AND aggregate_id = :id AND playhead > :playhead')
            ->getSQL();

        /** @var list<array{aggregate_id: string, playhead: string|int, event: class-string<AggregateChanged<array<string, mixed>>>, payload: string, recorded_on: string}> $result */
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
            static function (array $data) use ($platform, $aggregate) {
                $data['aggregate_class'] = $aggregate;

                return Message::deserialize(
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
            ->where('aggregate = :aggregate AND aggregate_id = :id')
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
     * @param list<Message> $messages
     */
    public function saveBatch(array $messages): void
    {
        $storeTableName = $this->storeTableName;

        $this->connection->transactional(
            function (Connection $connection) use ($messages, $storeTableName): void {
                foreach ($messages as $message) {
                    $shortName = $this->shortName($message->aggregateClass());

                    $data = $message->serialize();
                    unset($data['aggregate_class']);
                    $data['aggregate'] = $shortName;

                    $connection->insert(
                        $storeTableName,
                        $data,
                        [
                            'recorded_on' => Types::DATETIMETZ_IMMUTABLE,
                        ]
                    );
                }
            }
        );
    }

    /**
     * @return Generator<Message>
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
         *     aggregate_id: string,
         *     aggregate: string,
         *     playhead: string,
         *     event: class-string<AggregateChanged<array<string, mixed>>>,
         *     payload: string,
         *     recorded_on: string
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

            $data['aggregate_class'] = $classMap[$name];

            yield Message::deserialize(
                self::normalizeResult($platform, $data)
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

    public function save(Message $message): void
    {
        $data = $message->serialize();

        unset($data['aggregate_class']);
        $data['aggregate'] = $this->shortName($message->aggregateClass());

        $this->connection->insert(
            $this->storeTableName,
            $data,
            [
                'recorded_on' => Types::DATETIMETZ_IMMUTABLE,
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

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate', 'aggregate_id', 'playhead']);

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
