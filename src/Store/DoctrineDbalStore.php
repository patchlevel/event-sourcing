<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\EventBus\HeaderNotFound;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Serializer\EventSerializer;

use function array_fill;
use function array_filter;
use function array_values;
use function count;
use function floor;
use function implode;
use function in_array;
use function is_int;
use function is_string;
use function sprintf;

final class DoctrineDbalStore implements Store, ArchivableStore, DoctrineSchemaConfigurator
{
    /**
     * PostgreSQL has a limit of 65535 parameters in a single query.
     */
    private const MAX_UNSIGNED_SMALL_INT = 65_535;

    public function __construct(
        private readonly Connection $connection,
        private readonly EventSerializer $eventSerializer,
        private readonly HeadersSerializer $headersSerializer,
        private readonly string $storeTableName = 'eventstore',
    ) {
    }

    public function load(
        Criteria|null $criteria = null,
        int|null $limit = null,
        int|null $offset = null,
        bool $backwards = false,
    ): DoctrineDbalStoreStream {
        $builder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->storeTableName)
            ->orderBy('id', $backwards ? 'DESC' : 'ASC');

        $this->applyCriteria($builder, $criteria ?? new Criteria());

        $builder->setMaxResults($limit);
        $builder->setFirstResult($offset ?? 0);

        return new DoctrineDbalStoreStream(
            $this->connection->executeQuery(
                $builder->getSQL(),
                $builder->getParameters(),
                $builder->getParameterTypes(),
            ),
            $this->eventSerializer,
            $this->headersSerializer,
            $this->connection->getDatabasePlatform(),
        );
    }

    public function count(Criteria|null $criteria = null): int
    {
        $builder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->storeTableName);

        $this->applyCriteria($builder, $criteria ?? new Criteria());

        $result = $this->connection->fetchOne(
            $builder->getSQL(),
            $builder->getParameters(),
            $builder->getParameterTypes(),
        );

        if (!is_int($result) && !is_string($result)) {
            throw new WrongQueryResult();
        }

        return (int)$result;
    }

    private function applyCriteria(QueryBuilder $builder, Criteria $criteria): void
    {
        if ($criteria->aggregateName !== null) {
            $shortName = $criteria->aggregateName;
            $builder->andWhere('aggregate = :aggregate');
            $builder->setParameter('aggregate', $shortName);
        }

        if ($criteria->aggregateId !== null) {
            $builder->andWhere('aggregate_id = :id');
            $builder->setParameter('id', $criteria->aggregateId);
        }

        if ($criteria->fromPlayhead !== null) {
            $builder->andWhere('playhead > :playhead');
            $builder->setParameter('playhead', $criteria->fromPlayhead, Types::INTEGER);
        }

        if ($criteria->archived !== null) {
            $builder->andWhere('archived = :archived');
            $builder->setParameter('archived', $criteria->archived, Types::BOOLEAN);
        }

        if ($criteria->fromIndex === null) {
            return;
        }

        $builder->andWhere('id > :index');
        $builder->setParameter('index', $criteria->fromIndex, Types::INTEGER);
    }

    public function save(Message ...$messages): void
    {
        if ($messages === []) {
            return;
        }

        $this->connection->transactional(
            function (Connection $connection) use ($messages): void {
                $booleanType = Type::getType(Types::BOOLEAN);
                $jsonType = Type::getType(Types::JSON);
                $dateTimeType = Type::getType(Types::DATETIMETZ_IMMUTABLE);

                $columns = [
                    'aggregate',
                    'aggregate_id',
                    'playhead',
                    'event',
                    'payload',
                    'recorded_on',
                    'new_stream_start',
                    'archived',
                    'custom_headers',
                ];

                $columnsLength = count($columns);
                $batchSize = (int)floor(self::MAX_UNSIGNED_SMALL_INT / $columnsLength);
                $placeholder = implode(', ', array_fill(0, $columnsLength, '?'));

                $parameters = [];
                $placeholders = [];
                /** @var array<int<0, max>, Type> $types */
                $types = [];
                $position = 0;
                foreach ($messages as $message) {
                    /** @var int<0, max> $offset */
                    $offset = $position * $columnsLength;
                    $placeholders[] = $placeholder;

                    $data = $this->eventSerializer->serialize($message->event());

                    try {
                        $aggregateHeader = $message->header(AggregateHeader::class);

                        $parameters[] = $aggregateHeader->aggregateName;
                        $parameters[] = $aggregateHeader->aggregateId;
                        $parameters[] = $aggregateHeader->playhead;
                        $parameters[] = $data->name;
                        $parameters[] = $data->payload;

                        $parameters[] = $aggregateHeader->recordedOn;
                        $types[$offset + 5] = $dateTimeType;
                    } catch (HeaderNotFound $e) {
                        throw new MissingDataForStorage($e->name, $e);
                    }

                    try {
                        $newStreamStart = $message->header(NewStreamStartHeader::class)->newStreamStart;
                    } catch (HeaderNotFound) {
                        $newStreamStart = false;
                    }

                    $parameters[] = $newStreamStart;
                    $types[$offset + 6] = $booleanType;

                    try {
                        $archived = $message->header(ArchivedHeader::class)->archived;
                    } catch (HeaderNotFound) {
                        $archived = false;
                    }

                    $parameters[] = $archived;
                    $types[$offset + 7] = $booleanType;

                    $parameters[] = $this->headersSerializer->serialize($this->getCustomHeaders($message));
                    $types[$offset + 8] = $jsonType;

                    $position++;

                    if ($position !== $batchSize) {
                        continue;
                    }

                    $query = sprintf(
                        "INSERT INTO %s (%s) VALUES\n(%s)",
                        $this->storeTableName,
                        implode(', ', $columns),
                        implode("),\n(", $placeholders),
                    );

                    $connection->executeStatement($query, $parameters, $types);

                    $parameters = [];
                    $placeholders = [];
                    $types = [];

                    $position = 0;
                }

                if ($position === 0) {
                    return;
                }

                $query = sprintf(
                    "INSERT INTO %s (%s) VALUES\n(%s)",
                    $this->storeTableName,
                    implode(', ', $columns),
                    implode("),\n(", $placeholders),
                );

                try {
                    $connection->executeStatement($query, $parameters, $types);
                } catch (UniqueConstraintViolationException $e) {
                    throw new UniqueConstraintViolation($e);
                }
            },
        );
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

    public function archiveMessages(string $aggregateName, string $aggregateId, int $untilPlayhead): void
    {
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
        $statement->bindValue('aggregate_id', $aggregateId);
        $statement->bindValue('playhead', $untilPlayhead);

        $statement->executeQuery();
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
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('aggregate_id', Types::STRING)
            ->setLength(36)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('event', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recorded_on', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(true);
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

    /** @return list<object> */
    private function getCustomHeaders(Message $message): array
    {
        $filteredHeaders = [
            AggregateHeader::class,
            NewStreamStartHeader::class,
            ArchivedHeader::class,
        ];

        return array_values(
            array_filter(
                $message->headers(),
                static fn (object $header) => !in_array($header::class, $filteredHeaders, true),
            ),
        );
    }
}
