<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\TagCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use PDO;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;

use function array_fill;
use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function class_exists;
use function count;
use function explode;
use function floor;
use function implode;
use function in_array;
use function is_int;
use function is_string;
use function json_encode;
use function sprintf;
use function str_contains;
use function str_replace;

/** @experimental */
final class TaggableDoctrineDbalStore implements Store, AppendStore, SubscriptionStore, DoctrineSchemaConfigurator
{
    /**
     * PostgreSQL has a limit of 65535 parameters in a single query.
     */
    private const MAX_UNSIGNED_SMALL_INT = 65_535;

    /**
     * Default lock id for advisory lock.
     */
    private const DEFAULT_LOCK_ID = 133742;

    private readonly HeadersSerializer $headersSerializer;

    private readonly ClockInterface $clock;

    /** @var array{table_name: string, locking: bool, lock_id: int, lock_timeout: int, keep_index: bool, default_stream_name: string} */
    private readonly array $config;

    private bool $hasLock = false;

    private readonly bool $isMysql;

    private readonly bool $isMariaDb;

    private readonly bool $isPostgres;

    private readonly bool $isSQLite;

    /** @param array{table_name?: string, locking?: bool, lock_id?: int, lock_timeout?: int, keep_index?: bool, default_stream_name?: string} $config */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventSerializer $eventSerializer,
        private readonly EventRegistry $eventRegistry,
        HeadersSerializer|null $headersSerializer = null,
        ClockInterface|null $clock = null,
        array $config = [],
    ) {
        $this->headersSerializer = $headersSerializer ?? DefaultHeadersSerializer::createDefault();
        $this->clock = $clock ?? new SystemClock();

        $this->config = array_merge([
            'table_name' => 'event_store',
            'locking' => true,
            'lock_id' => self::DEFAULT_LOCK_ID,
            'lock_timeout' => -1,
            'keep_index' => false,
            'default_stream_name' => 'main',
        ], $config);

        $platform = $this->connection->getDatabasePlatform();

        $this->isMysql = $platform instanceof MySQLPlatform;
        $this->isMariaDb = $platform instanceof MariaDBPlatform;
        $this->isPostgres = $platform instanceof PostgreSQLPlatform;
        $this->isSQLite = $platform instanceof SQLitePlatform;
    }

    public function load(
        Criteria|null $criteria = null,
        int|null $limit = null,
        int|null $offset = null,
        bool $backwards = false,
    ): TaggableDoctrineDbalStoreStream {
        $builder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->config['table_name'])
            ->orderBy('id', $backwards ? 'DESC' : 'ASC');

        $this->applyCriteria($builder, $criteria ?? new Criteria());

        $builder->setMaxResults($limit);
        $builder->setFirstResult($offset ?? 0);

        return new TaggableDoctrineDbalStoreStream(
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
            ->from($this->config['table_name']);

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
        $criteriaList = $criteria->all();

        foreach ($criteriaList as $criterion) {
            switch ($criterion::class) {
                case StreamCriterion::class:
                    if ($criterion->all()) {
                        break;
                    }

                    $streamFilters = [];

                    foreach ($criterion->streamName as $index => $streamName) {
                        if (str_contains($streamName, '*')) {
                            $streamFilters[] = 'stream LIKE :stream_' . $index;
                            $builder->setParameter('stream_' . $index, str_replace('*', '%', $streamName));
                        } else {
                            $streamFilters[] = 'stream = :stream_' . $index;
                            $builder->setParameter('stream_' . $index, $streamName);
                        }
                    }

                    if ($streamFilters === []) {
                        break;
                    }

                    $builder->andWhere($builder->expr()->or(...$streamFilters));

                    break;
                case FromPlayheadCriterion::class:
                    $builder->andWhere('playhead > :from_playhead');
                    $builder->setParameter('from_playhead', $criterion->fromPlayhead, Types::INTEGER);
                    break;
                case ToPlayheadCriterion::class:
                    $builder->andWhere('playhead < :to_playhead');
                    $builder->setParameter('to_playhead', $criterion->toPlayhead, Types::INTEGER);
                    break;
                case ArchivedCriterion::class:
                    $builder->andWhere('archived = :archived');
                    $builder->setParameter('archived', $criterion->archived, Types::BOOLEAN);
                    break;
                case FromIndexCriterion::class:
                    $builder->andWhere('id > :from_index');
                    $builder->setParameter('from_index', $criterion->fromIndex, Types::INTEGER);
                    break;
                case ToIndexCriterion::class:
                    $builder->andWhere('id < :to_index');
                    $builder->setParameter('to_index', $criterion->toIndex, Types::INTEGER);
                    break;
                case EventsCriterion::class:
                    $builder->andWhere('event_name IN (:events)');
                    $builder->setParameter('events', $criterion->events, ArrayParameterType::STRING);
                    break;
                case EventIdCriterion::class:
                    $builder->andWhere('event_id = :event_id');
                    $builder->setParameter('event_id', $criterion->eventId, ArrayParameterType::STRING);
                    break;
                case TagCriterion::class:
                    if ($this->isSQLite) {
                        $builder->andWhere('NOT EXISTS(SELECT value FROM JSON_EACH(:tags) WHERE value NOT IN (SELECT value FROM JSON_EACH(tags)))');
                    } elseif ($this->isPostgres) {
                        $builder->andWhere('tags @> :tags::jsonb');
                    } elseif ($this->isMysql || $this->isMariaDb) {
                        $builder->andWhere('JSON_CONTAINS(tags, :tags)');
                    } else {
                        throw new RuntimeException('x');
                    }

                    $builder->setParameter('tags', json_encode($criterion->tags));
                    break;
                default:
                    throw new UnsupportedCriterion($criterion::class);
            }
        }
    }

    public function save(Message ...$messages): void
    {
        if ($messages === []) {
            return;
        }

        $this->transactional(
            function () use ($messages): void {
                $booleanType = Type::getType(Types::BOOLEAN);
                $dateTimeType = Type::getType(Types::DATETIMETZ_IMMUTABLE);
                $jsonType = Type::getType(Types::JSON);

                $columns = [
                    'stream',
                    'playhead',
                    'event_id',
                    'event_name',
                    'event_payload',
                    'tags',
                    'recorded_on',
                    'archived',
                    'custom_headers',
                ];

                if ($this->config['keep_index']) {
                    $columns[] = 'id';
                }

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

                    $parameters[] = $message->hasHeader(StreamNameHeader::class)
                        ? $message->header(StreamNameHeader::class)->streamName
                        : $this->config['default_stream_name'];

                    $parameters[] = $message->hasHeader(PlayheadHeader::class)
                        ? $message->header(PlayheadHeader::class)->playhead
                        : null;

                    $eventId = $message->hasHeader(EventIdHeader::class)
                        ? $message->header(EventIdHeader::class)->eventId
                        : Uuid::uuid7()->toString();

                    $parameters[] = $eventId;
                    $parameters[] = $data->name;
                    $parameters[] = $data->payload;

                    $parameters[] = $message->hasHeader(TagsHeader::class)
                        ? $message->header(TagsHeader::class)->tags
                        : [];

                    $types[$offset + 5] = $jsonType;

                    $parameters[] = $message->hasHeader(RecordedOnHeader::class)
                        ? $message->header(RecordedOnHeader::class)->recordedOn
                        : $this->clock->now();

                    $types[$offset + 6] = $dateTimeType;

                    $parameters[] = $message->hasHeader(ArchivedHeader::class);
                    $types[$offset + 7] = $booleanType;

                    $parameters[] = $this->headersSerializer->serialize($this->getCustomHeaders($message));

                    if ($this->config['keep_index']) {
                        try {
                            $parameters[] = $message->header(IndexHeader::class)->index;
                        } catch (HeaderNotFound $e) {
                            throw new MissingDataForStorage($e->name, $e);
                        }
                    }

                    $position++;

                    if ($position !== $batchSize) {
                        continue;
                    }

                    $this->executeSave($columns, $placeholders, $parameters, $types, $this->connection);

                    $parameters = [];
                    $placeholders = [];
                    $types = [];

                    $position = 0;
                }

                if ($position === 0) {
                    return;
                }

                $this->executeSave($columns, $placeholders, $parameters, $types, $this->connection);

                if (!$this->config['keep_index'] || !$this->isPostgres) {
                    return;
                }

                $this->connection->executeStatement(
                    sprintf(
                        "SELECT setval('%s', (SELECT MAX(id) FROM %s));",
                        sprintf('%s_id_seq', $this->config['table_name']),
                        $this->config['table_name'],
                    ),
                );
            },
        );
    }

    /** @param iterable<Message> $messages */
    public function append(iterable $messages, AppendCondition|null $appendCondition = null): void
    {
        $this->transactional(function () use ($messages, $appendCondition): void {
            $booleanType = Type::getType(Types::BOOLEAN);
            $dateTimeType = Type::getType(Types::DATETIMETZ_IMMUTABLE);
            $jsonType = Type::getType(Types::JSON);

            $columns = [
                'stream',
                'playhead',
                'event_id',
                'event_name',
                'event_payload',
                'tags',
                'recorded_on',
                'archived',
                'custom_headers',
            ];

            $selects = [];
            $parameters = [];
            $types = [];

            $position = 0;

            foreach ($messages as $message) {
                $selects[] = 'SELECT :stream' . $position
                    . ', :playhead' . $position . ($this->isPostgres ? '::int' : '')
                    . ', :event_id' . $position
                    . ', :event_name' . $position
                    . ', :event_payload' . $position . ($this->isPostgres ? '::jsonb' : '')
                    . ', :tags' . $position . ($this->isPostgres ? '::jsonb' : '')
                    . ', :recorded_on' . $position . ($this->isPostgres ? '::timestamptz' : '')
                    . ', :archived' . $position . ($this->isPostgres ? '::boolean' : '')
                    . ', :custom_headers' . $position . ($this->isPostgres ? '::jsonb' : '');

                $data = $this->eventSerializer->serialize($message->event());

                $parameters['stream' . $position] = $message->hasHeader(StreamNameHeader::class)
                    ? $message->header(StreamNameHeader::class)->streamName
                    : $this->config['default_stream_name'];

                $parameters['playhead' . $position] = $message->hasHeader(PlayheadHeader::class)
                    ? $message->header(PlayheadHeader::class)->playhead
                    : null;

                $eventId = $message->hasHeader(EventIdHeader::class)
                    ? $message->header(EventIdHeader::class)->eventId
                    : Uuid::uuid7()->toString();

                $parameters['event_id' . $position] = $eventId;
                $parameters['event_name' . $position] = $data->name;
                $parameters['event_payload' . $position] = $data->payload;

                $parameters['tags' . $position] = $message->hasHeader(TagsHeader::class)
                    ? $message->header(TagsHeader::class)->tags
                    : [];

                $types['tags' . $position] = $jsonType;

                $parameters['recorded_on' . $position] = $message->hasHeader(RecordedOnHeader::class)
                    ? $message->header(RecordedOnHeader::class)->recordedOn
                    : $this->clock->now();

                $types['recorded_on' . $position] = $dateTimeType;

                $parameters['archived' . $position] = $message->hasHeader(ArchivedHeader::class);
                $types['archived' . $position] = $booleanType;

                $parameters['custom_headers' . $position] = $this->headersSerializer->serialize($this->getCustomHeaders($message));

                $position++;
            }

            $query = sprintf(
                'INSERT INTO %s (%s) %s',
                $this->config['table_name'],
                implode(', ', $columns),
                implode(' UNION ALL ', $selects),
            );

            if ($appendCondition instanceof AppendCondition) {
                $queryBuilder = $this->connection->createQueryBuilder()
                    ->select('events.id')
                    ->from($this->config['table_name'], 'events')
                    ->orderBy('events.id', 'DESC')
                    ->setMaxResults(1);

                $this->queryCondition($queryBuilder, $appendCondition->query);

                if ($appendCondition->highestSequenceNumber === 0) {
                    $query .= ' WHERE NOT EXISTS (' . $queryBuilder->getSQL() . ')';
                } else {
                    $query .= ' WHERE (' . $queryBuilder->getSQL() . ') = :highestId';
                    $parameters['highestId'] = $appendCondition->highestSequenceNumber;
                }

                $parameters = array_merge(
                    $parameters,
                    $queryBuilder->getParameters(),
                );

                $types = array_merge(
                    $types,
                    $queryBuilder->getParameterTypes(),
                );
            }

            try {
                $affectedRows = $this->connection->executeStatement($query, $parameters, $types);
            } catch (UniqueConstraintViolationException $e) {
                throw new UniqueConstraintViolation($e);
            }

            if ($affectedRows === 0 && $appendCondition && $appendCondition->highestSequenceNumber !== null) {
                throw new AppendConditionNotMet($appendCondition);
            }
        });
    }

    public function query(Query $query): Stream
    {
        $builder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->config['table_name'], 'events')
            ->orderBy('events.id', 'ASC');

        $this->queryCondition($builder, $query);

        return new TaggableDoctrineDbalStoreStream(
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

    /**
     * @param Closure():ClosureReturn $function
     *
     * @template ClosureReturn
     */
    public function transactional(Closure $function): void
    {
        if ($this->hasLock || !$this->config['locking']) {
            $this->connection->transactional($function);
        } else {
            $this->connection->transactional(function () use ($function): void {
                $this->lock();
                try {
                    $function();
                } finally {
                    $this->unlock();
                }
            });
        }
    }

    /** @return list<string> */
    public function streams(): array
    {
        $builder = $this->connection->createQueryBuilder()
            ->select('stream')
            ->distinct()
            ->from($this->config['table_name'])
            ->orderBy('stream');

        /** @var list<string> $streams */
        $streams = $builder->fetchFirstColumn();

        return $streams;
    }

    public function remove(Criteria|null $criteria = null): void
    {
        $builder = $this->connection->createQueryBuilder();

        $builder->delete($this->config['table_name']);
        $this->applyCriteria($builder, $criteria ?? new Criteria());

        $builder->executeStatement();
    }

    public function archive(Criteria|null $criteria = null): void
    {
        $builder = $this->connection->createQueryBuilder();

        $builder->update($this->config['table_name']);

        $this->applyCriteria($builder, $criteria ?? new Criteria());

        $builder
            ->set('archived', ':value')
            ->setParameter('value', true, Types::BOOLEAN);

        $builder->executeStatement();
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        if ($this->connection !== $connection) {
            return;
        }

        $table = $schema->createTable($this->config['table_name']);

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true);
        $table->addColumn('stream', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(false);
        $table->addColumn('event_id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('event_name', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('event_payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recorded_on', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('archived', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('tags', Types::JSON)
            ->setPlatformOptions($this->isPostgres ? ['jsonb' => true] : [])
            ->setLength(255)
            ->setNotnull(false);

        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setColumnNames(
                UnqualifiedName::unquoted('id'),
            )->create(),
        );
        $table->addUniqueIndex(['event_id']);
        $table->addUniqueIndex(['stream', 'playhead']);
        $table->addIndex(['stream', 'playhead', 'archived']);
    }

    /** @return list<object> */
    private function getCustomHeaders(Message $message): array
    {
        $filteredHeaders = [
            IndexHeader::class,
            StreamNameHeader::class,
            EventIdHeader::class,
            PlayheadHeader::class,
            RecordedOnHeader::class,
            ArchivedHeader::class,
            TagsHeader::class,
        ];

        return array_values(
            array_filter(
                $message->headers(),
                static fn (object $header) => !in_array($header::class, $filteredHeaders, true),
            ),
        );
    }

    public function supportSubscription(): bool
    {
        return $this->isPostgres && class_exists(PDO::class);
    }

    public function wait(int $timeoutMilliseconds): void
    {
        if (!$this->supportSubscription()) {
            return;
        }

        $this->connection->executeStatement(sprintf('LISTEN "%s"', $this->config['table_name']));

        /** @var PDO $nativeConnection */
        $nativeConnection = $this->connection->getNativeConnection();

        $nativeConnection->pgsqlGetNotify(PDO::FETCH_ASSOC, $timeoutMilliseconds);
    }

    public function setupSubscription(): void
    {
        if (!$this->supportSubscription()) {
            return;
        }

        $functionName = $this->createTriggerFunctionName();

        $this->connection->executeStatement(sprintf(
            <<<'SQL'
                CREATE OR REPLACE FUNCTION %1$s() RETURNS TRIGGER AS $$
                    BEGIN
                        PERFORM pg_notify('%2$s', NEW.stream::text);
                        RETURN NEW;
                    END;
                $$ LANGUAGE plpgsql;
                SQL,
            $functionName,
            $this->config['table_name'],
        ));

        $this->connection->executeStatement(sprintf(
            'DROP TRIGGER IF EXISTS notify_trigger ON %s;',
            $this->config['table_name'],
        ));
        $this->connection->executeStatement(sprintf(
            'CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON %1$s FOR EACH ROW EXECUTE PROCEDURE %2$s();',
            $this->config['table_name'],
            $functionName,
        ));
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    private function createTriggerFunctionName(): string
    {
        $tableConfig = explode('.', $this->config['table_name']);

        if (count($tableConfig) === 1) {
            return sprintf('notify_%1$s', $tableConfig[0]);
        }

        return sprintf('%1$s.notify_%2$s', $tableConfig[0], $tableConfig[1]);
    }

    /**
     * @param array<string>               $columns
     * @param array<string>               $placeholders
     * @param list<mixed>                 $parameters
     * @param array<0|positive-int, Type> $types
     */
    private function executeSave(
        array $columns,
        array $placeholders,
        array $parameters,
        array $types,
        Connection $connection,
    ): void {
        $query = sprintf(
            "INSERT INTO %s (%s) VALUES\n(%s)",
            $this->config['table_name'],
            implode(', ', $columns),
            implode("),\n(", $placeholders),
        );

        try {
            $connection->executeStatement($query, $parameters, $types);
        } catch (UniqueConstraintViolationException $e) {
            throw new UniqueConstraintViolation($e);
        }
    }

    private function lock(): void
    {
        $this->hasLock = true;

        if ($this->isPostgres) {
            $this->connection->executeStatement(
                sprintf(
                    'SELECT pg_advisory_xact_lock(%s)',
                    $this->config['lock_id'],
                ),
            );

            return;
        }

        if ($this->isMariaDb || $this->isMysql) {
            $this->connection->fetchAllAssociative(
                sprintf(
                    'SELECT GET_LOCK("%s", %d)',
                    $this->config['lock_id'],
                    $this->config['lock_timeout'],
                ),
            );

            return;
        }

        if ($this->isSQLite) {
            return; // sql locking is not needed because of file locking
        }

        throw new LockingNotImplemented(
            $this->connection->getDatabasePlatform()::class,
        );
    }

    private function unlock(): void
    {
        $this->hasLock = false;

        if ($this->isPostgres) {
            return; // lock is released automatically after transaction
        }

        if ($this->isMariaDb || $this->isMysql) {
            $this->connection->fetchAllAssociative(
                sprintf(
                    'SELECT RELEASE_LOCK("%s")',
                    $this->config['lock_id'],
                ),
            );

            return;
        }

        if ($this->isSQLite) {
            return; // sql locking is not needed because of file locking
        }

        throw new LockingNotImplemented(
            $this->connection->getDatabasePlatform()::class,
        );
    }

    /** @return Closure(): string */
    private function uniqueParameterGenerator(): Closure
    {
        return static function () {
            /** @var int $counter */
            static $counter = 0;

            return 'param' . ++$counter;
        };
    }

    private function queryCondition(QueryBuilder $builder, Query $query): void
    {
        if ($query->subQueries === []) {
            return;
        }

        $subqueries = [];

        $uniqueParameterGenerator = $this->uniqueParameterGenerator();

        foreach ($query->subQueries as $subQuery) {
            if ($subQuery->empty()) {
                continue;
            }

            $subQueryBuilder = $this->connection->createQueryBuilder()
                ->select('id')
                ->from($this->config['table_name']);

            if ($subQuery->streamName !== null) {
                $streamNameParameterName = $uniqueParameterGenerator();

                $subQueryBuilder->andWhere("stream = :{$streamNameParameterName}");
                $builder->setParameter($streamNameParameterName, $subQuery->streamName);
            }

            if ($subQuery->tags !== []) {
                $tagParameterName = $uniqueParameterGenerator();

                if ($this->isSQLite) {
                    $subQueryBuilder->andWhere("NOT EXISTS(SELECT value FROM JSON_EACH(:{$tagParameterName}) WHERE value NOT IN (SELECT value FROM JSON_EACH(tags)))");
                } elseif ($this->isPostgres) {
                    $subQueryBuilder->andWhere("tags @> :{$tagParameterName}::jsonb");
                } elseif ($this->isMysql || $this->isMariaDb) {
                    $subQueryBuilder->andWhere("JSON_CONTAINS(tags, :{$tagParameterName})");
                } else {
                    throw new RuntimeException('x');
                }

                $builder->setParameter($tagParameterName, json_encode($subQuery->tags));
            }

            if ($subQuery->events !== []) {
                $eventParameterName = $uniqueParameterGenerator();

                $subQueryBuilder->andWhere("event_name IN (:{$eventParameterName})");

                $builder->setParameter(
                    $eventParameterName,
                    array_map(
                        fn (string $event) => $this->eventRegistry->eventName($event),
                        $subQuery->events,
                    ),
                    ArrayParameterType::STRING,
                );
            }

            if ($subQuery->onlyLastEvent) {
                $subQueryBuilder->select('MAX(id) AS id');
            }

            $subqueries[] = $subQueryBuilder->getSQL();
        }

        if ($subqueries === []) {
            return;
        }

        $joinQueryBuilder = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('(' . implode(' UNION ALL ', $subqueries) . ')', 'j')
            ->groupBy('j.id');

        $builder->innerJoin(
            'events',
            '(' . $joinQueryBuilder->getSQL() . ')',
            'ej',
            'ej.id = events.id',
        );
    }
}
