<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use PDO;
use Psr\Clock\ClockInterface;

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
use function mb_substr;
use function sprintf;
use function str_contains;
use function str_ends_with;

final class StreamDoctrineDbalStore implements StreamStore, SubscriptionStore, DoctrineSchemaConfigurator
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

    /** @var array{table_name: string, locking: bool, lock_id: int, lock_timeout: int} */
    private readonly array $config;

    private bool $hasLock = false;

    /** @param array{table_name?: string, locking?: bool, lock_id?: int, lock_timeout?: int} $config */
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
        ], $config);
    }

    public function load(
        Criteria|null $criteria = null,
        int|null $limit = null,
        int|null $offset = null,
        bool $backwards = false,
    ): StreamDoctrineDbalStoreStream {
        $builder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->config['table_name'])
            ->orderBy('id', $backwards ? 'DESC' : 'ASC');

        $this->applyCriteria($builder, $criteria ?? new Criteria());

        $builder->setMaxResults($limit);
        $builder->setFirstResult($offset ?? 0);

        return new StreamDoctrineDbalStoreStream(
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
                    if ($criterion->streamName === '*') {
                        break;
                    }

                    if (str_ends_with($criterion->streamName, '*')) {
                        $streamName = mb_substr($criterion->streamName, 0, -1);

                        if (str_contains($streamName, '*')) {
                            throw new InvalidStreamName($criterion->streamName);
                        }

                        $builder->andWhere('stream LIKE :stream');
                        $builder->setParameter('stream', $streamName . '%');

                        break;
                    }

                    $builder->andWhere('stream = :stream');
                    $builder->setParameter('stream', $criterion->streamName);
                    break;
                case FromPlayheadCriterion::class:
                    $builder->andWhere('playhead > :playhead');
                    $builder->setParameter('playhead', $criterion->fromPlayhead, Types::INTEGER);
                    break;
                case ArchivedCriterion::class:
                    $builder->andWhere('archived = :archived');
                    $builder->setParameter('archived', $criterion->archived, Types::BOOLEAN);
                    break;
                case FromIndexCriterion::class:
                    $builder->andWhere('id > :index');
                    $builder->setParameter('index', $criterion->fromIndex, Types::INTEGER);
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
                /** @var array<string, int> $achievedUntilPlayhead */
                $achievedUntilPlayhead = [];

                $booleanType = Type::getType(Types::BOOLEAN);
                $dateTimeType = Type::getType(Types::DATETIMETZ_IMMUTABLE);

                $columns = [
                    'stream',
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
                        $streamHeader = $message->header(StreamHeader::class);
                    } catch (HeaderNotFound $e) {
                        throw new MissingDataForStorage($e->name, $e);
                    }

                    $parameters[] = $streamHeader->streamName;
                    $parameters[] = $streamHeader->playhead;
                    $parameters[] = $data->name;
                    $parameters[] = $data->payload;

                    $parameters[] = $streamHeader->recordedOn ?: $this->clock->now();
                    $types[$offset + 4] = $dateTimeType;

                    $streamStart = $message->hasHeader(StreamStartHeader::class);

                    if ($streamStart) {
                        $achievedUntilPlayhead[$streamHeader->streamName] = $streamHeader->playhead;
                    }

                    $parameters[] = $streamStart;
                    $types[$offset + 5] = $booleanType;

                    $parameters[] = $message->hasHeader(ArchivedHeader::class);
                    $types[$offset + 6] = $booleanType;

                    $parameters[] = $this->headersSerializer->serialize($this->getCustomHeaders($message));

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

                if ($position !== 0) {
                    $this->executeSave($columns, $placeholders, $parameters, $types, $this->connection);
                }

                foreach ($achievedUntilPlayhead as $stream => $playhead) {
                    $this->connection->executeStatement(
                        sprintf(
                            <<<'SQL'
                            UPDATE %s
                            SET archived = true
                            WHERE stream = :stream
                            AND playhead < :playhead
                            AND archived = false
                            SQL,
                            $this->config['table_name'],
                        ),
                        [
                            'stream' => $stream,
                            'playhead' => $playhead,
                        ],
                    );
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

        return $builder->fetchFirstColumn();
    }

    public function remove(string $streamName): void
    {
        $builder = $this->connection->createQueryBuilder()
            ->delete($this->config['table_name'])
            ->andWhere('stream = :stream')
            ->setParameter('stream', $streamName);

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
        $table->addUniqueIndex(['stream', 'playhead']);
        $table->addIndex(['stream', 'playhead', 'archived']);
    }

    /** @return list<object> */
    private function getCustomHeaders(Message $message): array
    {
        $filteredHeaders = [
            StreamHeader::class,
            StreamStartHeader::class,
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
                        PERFORM pg_notify('%2$s', 'update');
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
            $this->connection->fetchAllAssociative(
                sprintf(
                    'SELECT GET_LOCK("%s", %d)',
                    $this->config['lock_id'],
                    $this->config['lock_timeout'],
                ),
            );

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
            $this->connection->fetchAllAssociative(
                sprintf(
                    'SELECT RELEASE_LOCK("%s")',
                    $this->config['lock_id'],
                ),
            );

            return;
        }

        if ($platform instanceof SQLitePlatform) {
            return; // sql locking is not needed because of file locking
        }

        throw new LockingNotImplemented($platform::class);
    }
}
