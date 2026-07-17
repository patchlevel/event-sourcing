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
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Generator;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Schema\DoctrineHelper;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use PDO;
use Pdo\Pgsql;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;

use function array_fill;
use function array_filter;
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
use function sprintf;
use function str_contains;
use function str_replace;

use const PHP_VERSION_ID;

final class StreamDoctrineDbalStore implements Store, SubscriptionStore, DoctrineSchemaConfigurator
{
    /**
     * PostgreSQL has a limit of 65535 parameters in a single query.
     */
    private const MAX_UNSIGNED_SMALL_INT = 65_535;

    /**
     * Default lock id for advisory lock.
     */
    private const DEFAULT_LOCK_ID = 133742;

    /**
     * MariaDB does not support an infinite (negative) lock timeout. Very large values such as
     * PHP_INT_MAX overflow its internal timeout arithmetic and make GET_LOCK return NULL. We
     * therefore use a large but safe value (INT32_MAX minus a small buffer) as "effectively
     * infinite" wait.
     */
    private const INFINITE_MARIADB_LOCK_TIMEOUT = 2_147_482_647;

    private readonly HeadersSerializer $headersSerializer;

    private readonly ClockInterface $clock;

    /** @var array{table_name: string, locking: bool, lock_id: int, lock_timeout: int, keep_index: bool} */
    private readonly array $config;

    private bool $hasLock = false;

    /** @param array{table_name?: string, locking?: bool, lock_id?: int, lock_timeout?: int, keep_index?: bool} $config */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventSerializer $eventSerializer,
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
        ], $config);
    }

    public function load(
        Criteria|null $criteria = null,
        int|null $limit = null,
        int|null $offset = null,
        bool $backwards = false,
    ): Stream {
        $builder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->config['table_name'])
            ->orderBy('id', $backwards ? 'DESC' : 'ASC');

        $this->applyCriteria($builder, $criteria ?? new Criteria());

        $builder->setMaxResults($limit);
        $builder->setFirstResult($offset ?? 0);

        return new Stream(
            $this->buildGenerator(
                $this->connection->executeQuery(
                    $builder->getSQL(),
                    $builder->getParameters(),
                    $builder->getParameterTypes(),
                ),
            ),
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

                $columns = [
                    'stream',
                    'playhead',
                    'event_id',
                    'event_name',
                    'event_payload',
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

                    try {
                        $streamName = $message->header(StreamNameHeader::class)->streamName;
                        $parameters[] = $streamName;
                    } catch (HeaderNotFound $e) {
                        throw new MissingDataForStorage($e->name, $e);
                    }

                    if ($message->hasHeader(PlayheadHeader::class)) {
                        $parameters[] = $message->header(PlayheadHeader::class)->playhead;
                    } else {
                        $parameters[] = null;
                    }

                    if ($message->hasHeader(EventIdHeader::class)) {
                        $eventId = $message->header(EventIdHeader::class)->eventId;
                    } else {
                        $eventId = Uuid::uuid7()->toString();
                    }

                    $parameters[] = $eventId;
                    $parameters[] = $data->name;
                    $parameters[] = $data->payload;

                    if ($message->hasHeader(RecordedOnHeader::class)) {
                        $parameters[] = $message->header(RecordedOnHeader::class)->recordedOn;
                    } else {
                        $parameters[] = $this->clock->now();
                    }

                    $types[$offset + 5] = $dateTimeType;

                    $parameters[] = $message->hasHeader(ArchivedHeader::class);
                    $types[$offset + 6] = $booleanType;

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

                if (!$this->config['keep_index'] || !($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform)) {
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
        if (!DoctrineHelper::sameDatabase($this->connection, $connection)) {
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
        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
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
        return $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform && class_exists(PDO::class);
    }

    public function wait(int $timeoutMilliseconds): void
    {
        if (!$this->supportSubscription()) {
            return;
        }

        $this->connection->executeStatement(sprintf('LISTEN "%s"', $this->config['table_name']));

        if (PHP_VERSION_ID >= 80400) {
            /** @var Pgsql $nativeConnection */
            $nativeConnection = $this->connection->getNativeConnection();
            $nativeConnection->getNotify(PDO::FETCH_ASSOC, $timeoutMilliseconds);
        } else {
            /** @var PDO $nativeConnection */
            $nativeConnection = $this->connection->getNativeConnection();
            $nativeConnection->pgsqlGetNotify(PDO::FETCH_ASSOC, $timeoutMilliseconds);
        }
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

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $this->connection->executeStatement(
                sprintf(
                    'SELECT pg_advisory_xact_lock(%s)',
                    $this->config['lock_id'],
                ),
            );

            return;
        }

        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform) {
            $lockTimeout = $this->config['lock_timeout'];

            if ($platform instanceof MariaDBPlatform && $lockTimeout < 0) {
                $lockTimeout = self::INFINITE_MARIADB_LOCK_TIMEOUT;
            }

            $result = $this->connection->fetchOne(
                sprintf(
                    'SELECT GET_LOCK("%s", %d)',
                    $this->config['lock_id'],
                    $lockTimeout,
                ),
            );

            if ($result === 0) {
                throw LockCouldNotBeAcquired::byTimeout($this->config['lock_id'], $this->config['lock_timeout']);
            }

            if ($result !== 1) {
                throw LockCouldNotBeAcquired::byError($this->config['lock_id']);
            }

            return;
        }

        if ($platform instanceof SQLitePlatform) {
            return; // sql locking is not needed because of file locking
        }

        throw new LockingNotImplemented($platform::class);
    }

    private function unlock(): void
    {
        $this->hasLock = false;

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            return; // lock is released automatically after transaction
        }

        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform) {
            $result = $this->connection->fetchOne(
                sprintf(
                    'SELECT RELEASE_LOCK("%s")',
                    $this->config['lock_id'],
                ),
            );

            if ($result === 0) {
                throw LockCouldNotBeFreed::notOurs($this->config['lock_id']);
            }

            if ($result !== 1) {
                throw LockCouldNotBeFreed::notExist($this->config['lock_id']);
            }

            return;
        }

        if ($platform instanceof SQLitePlatform) {
            return; // sql locking is not needed because of file locking
        }

        throw new LockingNotImplemented($platform::class);
    }

    /** @return Generator<int, Message> */
    private function buildGenerator(Result $result): Generator
    {
        /** @var DateTimeTzImmutableType $dateTimeType */
        $dateTimeType = Type::getType(Types::DATETIMETZ_IMMUTABLE);
        $platform = $this->connection->getDatabasePlatform();

        /** @var array{id: positive-int, stream: string, playhead: int|string|null, event_id: string, event_name: string, event_payload: string, recorded_on: string, archived: int|string, custom_headers: string} $data */
        foreach ($result->iterateAssociative() as $data) {
            $event = $this->eventSerializer->deserialize(new SerializedEvent(
                $data['event_name'],
                $data['event_payload'],
            ));

            $message = Message::create($event)
                ->withHeader(new IndexHeader($data['id']))
                ->withHeader(new StreamNameHeader($data['stream']))
                ->withHeader(new RecordedOnHeader($dateTimeType->convertToPHPValue($data['recorded_on'], $platform)))
                ->withHeader(new EventIdHeader($data['event_id']));

            if ($data['playhead'] !== null) {
                $message = $message->withHeader(new PlayheadHeader((int)$data['playhead']));
            }

            if ($data['archived']) {
                $message = $message->withHeader(new ArchivedHeader());
            }

            $customHeaders = $this->headersSerializer->deserialize($data['custom_headers']);

            yield $data['id'] => $message->withHeaders($customHeaders);
        }
    }
}
