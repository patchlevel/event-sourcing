<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Schema\DoctrineHelper;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Psr\Clock\ClockInterface;
use ReflectionClass;

use function array_chunk;
use function array_fill;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function assert;
use function count;
use function implode;
use function in_array;
use function json_decode;
use function json_encode;
use function serialize;
use function sort;
use function sprintf;
use function unserialize;

use const JSON_THROW_ON_ERROR;

/** @phpstan-type Data = array{
 *     id: string,
 *     group_name: string,
 *     run_mode: string,
 *     position: int|null,
 *     status: string,
 *     error_message: string|null,
 *     error_previous_status: string|null,
 *     error_context: string|null,
 *     retry_attempt: int,
 *     last_saved_at: string,
 *     cleanup_tasks: string|null,
 *     subscriber_id: string,
 *     partition_key: string|null,
 * }
 * @phpstan-type LightData = array{
 *     id: string,
 *     group_name: string,
 *     run_mode: string,
 *     position: int|string|null,
 *     status: string,
 *     error_message: string|null,
 *     error_previous_status: string|null,
 *     retry_attempt: int|string,
 *     last_saved_at: string,
 *     subscriber_id: string,
 *     partition_key: string|null,
 *     has_error_context: int|string,
 *     has_cleanup_tasks: int|string,
 * }
 * @phpstan-import-type Context from SubscriptionError
 */
final class DoctrineSubscriptionStore implements PartitionInboxStore, DoctrineSchemaConfigurator
{
    /** How many inbox rows or conditions go into one statement, far below the parameter limits of the databases. */
    private const INBOX_CHUNK = 500;

    /** How many partitions are read with one union, SQLite allows at most 500 parts. */
    private const INBOX_UNION_CHUNK = 100;

    /** How many subscriptions go into one insert or update, 100 rows stay below 3000 parameters. */
    private const ROW_CHUNK = 100;

    /** find() leaves out error_context and cleanup_tasks, see createLazySubscription(). */
    private const LAZY_LOADED_COLUMNS = [
        'id',
        'group_name',
        'run_mode',
        'position',
        'status',
        'error_message',
        'error_previous_status',
        'retry_attempt',
        'last_saved_at',
        'subscriber_id',
        'partition_key',
    ];

    /** In the order of the insert. */
    private const COLUMN_TYPES = [
        'id' => ParameterType::STRING,
        'group_name' => ParameterType::STRING,
        'run_mode' => ParameterType::STRING,
        'status' => ParameterType::STRING,
        'position' => ParameterType::INTEGER,
        'error_message' => ParameterType::STRING,
        'error_previous_status' => ParameterType::STRING,
        'error_context' => ParameterType::STRING,
        'retry_attempt' => ParameterType::INTEGER,
        'last_saved_at' => Types::DATETIME_IMMUTABLE,
        'cleanup_tasks' => ParameterType::STRING,
        'subscriber_id' => ParameterType::STRING,
        'partition_key' => ParameterType::STRING,
    ];

    /** The columns an update may change, the id, the subscriber id and the partition key never change. */
    private const UPDATE_COLUMNS = [
        'group_name',
        'run_mode',
        'status',
        'position',
        'error_message',
        'error_previous_status',
        'error_context',
        'retry_attempt',
        'last_saved_at',
        'cleanup_tasks',
    ];

    private const POSTGRES_CASTS = [
        'group_name' => 'VARCHAR',
        'run_mode' => 'VARCHAR',
        'status' => 'VARCHAR',
        'position' => 'INTEGER',
        'error_message' => 'TEXT',
        'error_previous_status' => 'VARCHAR',
        'error_context' => 'JSON',
        'retry_attempt' => 'INTEGER',
        'last_saved_at' => 'TIMESTAMP(0) WITH TIME ZONE',
        'cleanup_tasks' => 'TEXT',
    ];

    private readonly string $inboxTableName;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly string $tableName = 'subscriptions',
        string|null $inboxTableName = null,
    ) {
        $this->inboxTableName = $inboxTableName ?? $tableName . '_inbox';
    }

    public function get(string $subscriptionId): Subscription
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('id = :id')
            ->getSQL();

        /** @var Data|false $result */
        $result = $this->connection->fetchAssociative($sql, ['id' => $subscriptionId]);

        if ($result === false) {
            throw new SubscriptionNotFound($subscriptionId);
        }

        return $this->createSubscription($result);
    }

    /**
     * The error context and the cleanup tasks are loaded with one query on first access, see createLazySubscription().
     * With pendingPartitionsOnly, the main subscriptions come first, then the partitions with messages in their inbox.
     *
     * @return list<Subscription>
     */
    public function find(SubscriptionCriteria|null $criteria = null): array
    {
        if ($criteria === null || !$criteria->pendingPartitionsOnly) {
            return $this->fetch($criteria);
        }

        $mains = $criteria->partitions !== true ? $this->fetch($criteria, false) : [];
        $partitions = $criteria->partitions !== false ? $this->fetch($criteria, true) : [];

        return [...$mains, ...$partitions];
    }

    /**
     * Partitions with pending messages are found by joining the inbox, which only holds the unprocessed messages.
     * So the query costs as much as there is work, not as many partitions as there are.
     *
     * @return list<Subscription>
     */
    private function fetch(SubscriptionCriteria|null $criteria, bool|null $pendingPartitions = null): array
    {
        // the error context and the cleanup tasks can be large, only whether they exist is loaded right away
        $prefix = $pendingPartitions === true ? $this->tableName . '.' : '';
        $columns = array_map(static fn (string $column): string => $prefix . $column, self::LAZY_LOADED_COLUMNS);
        $columns[] = sprintf('CASE WHEN %serror_context IS NULL THEN 0 ELSE 1 END AS has_error_context', $prefix);
        $columns[] = sprintf('CASE WHEN %scleanup_tasks IS NULL THEN 0 ELSE 1 END AS has_cleanup_tasks', $prefix);

        $qb = $this->connection->createQueryBuilder()
            ->select(...$columns)
            ->from($this->tableName)
            ->orderBy($pendingPartitions === true ? $this->tableName . '.id' : 'id');

        if ($criteria !== null) {
            $this->applyCriteria($qb, $criteria);
        }

        if ($pendingPartitions === false) {
            $qb->andWhere('partition_key IS NULL');
        }

        if ($pendingPartitions === true) {
            $qb->andWhere('partition_key IS NOT NULL')
                ->innerJoin(
                    $this->tableName,
                    sprintf(
                        '(SELECT subscription_id, MAX(message_index) AS last_index FROM %s GROUP BY subscription_id)',
                        $this->inboxTableName,
                    ),
                    'pending',
                    sprintf(
                        'pending.subscription_id = %1$s.id AND pending.last_index > %1$s.position',
                        $this->tableName,
                    ),
                );
        }

        /** @var list<LightData> $result */
        $result = $qb->fetchAllAssociative();

        return array_map(
            fn (array $data) => $this->createLazySubscription($data),
            $result,
        );
    }

    /**
     * @param non-empty-list<string> $ids
     *
     * @return list<Subscription>
     */
    public function claim(array $ids, SubscriptionCriteria $criteria): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('id IN (:claim_ids)')
            ->setParameter('claim_ids', $ids, ArrayParameterType::STRING)
            ->orderBy('id');

        $this->applyCriteria($qb, $criteria);
        $this->applyPending($qb, $criteria);

        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $qb->forUpdate(ConflictResolutionMode::SKIP_LOCKED);
        }

        /** @var list<Data> $result */
        $result = $qb->fetchAllAssociative();

        return array_map(
            fn (array $data) => $this->createSubscription($data),
            $result,
        );
    }

    /** A partition is only claimed if its inbox has messages after its position. */
    private function applyPending(QueryBuilder $qb, SubscriptionCriteria $criteria): void
    {
        if (!$criteria->pendingPartitionsOnly) {
            return;
        }

        $qb->andWhere(sprintf(
            '%1$s.partition_key IS NULL OR EXISTS (SELECT 1 FROM %2$s WHERE %2$s.subscription_id = %1$s.id AND %2$s.message_index > %1$s.position)',
            $this->tableName,
            $this->inboxTableName,
        ));
    }

    private function applyCriteria(QueryBuilder $qb, SubscriptionCriteria $criteria): void
    {
        if ($criteria->ids !== null) {
            $qb->andWhere('id IN (:ids) OR subscriber_id IN (:subscriber_ids)')
                ->setParameter(
                    'ids',
                    $criteria->ids,
                    ArrayParameterType::STRING,
                )
                ->setParameter(
                    'subscriber_ids',
                    $criteria->ids,
                    ArrayParameterType::STRING,
                );
        }

        if ($criteria->groups !== null) {
            $qb->andWhere('group_name IN (:groups)')
                ->setParameter(
                    'groups',
                    $criteria->groups,
                    ArrayParameterType::STRING,
                );
        }

        if ($criteria->status !== null) {
            $qb->andWhere('status IN (:status)')
                ->setParameter(
                    'status',
                    array_map(static fn (Status $status) => $status->value, $criteria->status),
                    ArrayParameterType::STRING,
                );
        }

        if ($criteria->partitions === null) {
            return;
        }

        $qb->andWhere($criteria->partitions ? 'partition_key IS NOT NULL' : 'partition_key IS NULL');
    }

    public function add(Subscription ...$subscriptions): void
    {
        $subscriptions = array_values($subscriptions);

        if ($subscriptions === []) {
            return;
        }

        if (count($subscriptions) === 1) {
            $this->insert($subscriptions[0]);

            return;
        }

        $this->insertAll($subscriptions);
    }

    /** The inbox of an updated partition is cleared up to its position. */
    public function update(Subscription ...$subscriptions): void
    {
        $subscriptions = array_values($subscriptions);

        if ($subscriptions === []) {
            return;
        }

        if (count($subscriptions) === 1) {
            $this->updateOne($subscriptions[0]);
        } else {
            $this->updateAll($subscriptions);
        }

        $processed = [];

        foreach ($subscriptions as $subscription) {
            $position = $subscription->position();

            if (!$subscription->isPartition() || $position === null) {
                continue;
            }

            $processed[$subscription->id()] = $position;
        }

        $this->removeFromInbox($processed);
    }

    /** The inbox of a removed partition is removed as well. */
    public function remove(Subscription ...$subscriptions): void
    {
        $ids = array_map(static fn (Subscription $subscription): string => $subscription->id(), array_values($subscriptions));

        // removing a subscriber removes all its partitions, one statement per chunk instead of per partition
        foreach (array_chunk($ids, self::INBOX_CHUNK) as $chunk) {
            $this->connection->executeStatement(
                sprintf('DELETE FROM %s WHERE id IN (?)', $this->tableName),
                [$chunk],
                [ArrayParameterType::STRING],
            );
            $this->connection->executeStatement(
                sprintf('DELETE FROM %s WHERE subscription_id IN (?)', $this->inboxTableName),
                [$chunk],
                [ArrayParameterType::STRING],
            );
        }
    }

    private function insert(Subscription $subscription): void
    {
        $subscription->updateLastSavedAt($this->clock->now());

        try {
            $this->connection->insert(
                $this->tableName,
                $this->row($subscription),
                [
                    'last_saved_at' => Types::DATETIME_IMMUTABLE,
                ],
            );
        } catch (UniqueConstraintViolationException $e) {
            throw new SubscriptionAlreadyExists($subscription->id(), $e);
        }
    }

    /** @param non-empty-list<Subscription> $subscriptions */
    private function insertAll(array $subscriptions): void
    {
        $now = $this->clock->now();

        foreach (array_chunk($subscriptions, self::ROW_CHUNK) as $chunk) {
            $values = [];
            $parameters = [];
            $types = [];

            foreach ($chunk as $subscription) {
                $subscription->updateLastSavedAt($now);

                $row = $this->row($subscription);
                $values[] = '(' . implode(', ', array_fill(0, count($row), '?')) . ')';

                foreach ($row as $column => $value) {
                    $parameters[] = $value;
                    $types[] = self::COLUMN_TYPES[$column];
                }
            }

            try {
                $this->connection->executeStatement(
                    sprintf(
                        'INSERT INTO %s (%s) VALUES %s',
                        $this->tableName,
                        implode(', ', array_keys(self::COLUMN_TYPES)),
                        implode(', ', $values),
                    ),
                    $parameters,
                    $types,
                );
            } catch (UniqueConstraintViolationException $e) {
                throw new SubscriptionAlreadyExists(
                    implode('", "', array_map(static fn (Subscription $subscription): string => $subscription->id(), $chunk)),
                    $e,
                );
            }
        }
    }

    private function updateOne(Subscription $subscription): void
    {
        $subscription->updateLastSavedAt($this->clock->now());

        $row = $this->row($subscription);
        unset($row['id'], $row['subscriber_id'], $row['partition_key']);

        $effectedRows = $this->connection->update(
            $this->tableName,
            $row,
            [
                'id' => $subscription->id(),
            ],
            [
                'last_saved_at' => Types::DATETIME_IMMUTABLE,
            ],
        );

        if ($effectedRows !== 0) {
            return;
        }

        // mysql and mariadb count the changed rows, not the matched ones.
        // so an update without any change also affects no rows, even if the subscription exists.
        $exists = $this->connection->fetchOne(
            $this->connection->createQueryBuilder()
                ->select('1')
                ->from($this->tableName)
                ->where('id = :id')
                ->getSQL(),
            ['id' => $subscription->id()],
        );

        if ($exists === false) {
            throw new SubscriptionNotFound($subscription->id());
        }
    }

    /**
     * One UPDATE per chunk with a CASE per column, the only form of a batch update that all supported databases share.
     *
     * @param non-empty-list<Subscription> $subscriptions
     */
    private function updateAll(array $subscriptions): void
    {
        $now = $this->clock->now();
        $isPostgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;

        foreach (array_chunk($subscriptions, self::ROW_CHUNK) as $chunk) {
            $rows = [];

            foreach ($chunk as $subscription) {
                $subscription->updateLastSavedAt($now);
                $rows[$subscription->id()] = $this->row($subscription);
            }

            $assignments = [];
            $parameters = [];
            $types = [];

            foreach (self::UPDATE_COLUMNS as $column) {
                $cases = [];

                foreach ($rows as $id => $row) {
                    // postgres can not infer the type of a parameter in a CASE and would assign text to every column
                    $cases[] = $isPostgres
                        ? sprintf('WHEN ? THEN CAST(? AS %s)', self::POSTGRES_CASTS[$column])
                        : 'WHEN ? THEN ?';

                    $parameters[] = (string)$id;
                    $parameters[] = $row[$column];
                    $types[] = ParameterType::STRING;
                    $types[] = self::COLUMN_TYPES[$column];
                }

                $assignments[] = sprintf('%s = CASE id %s END', $column, implode(' ', $cases));
            }

            $ids = array_keys($rows);

            foreach ($ids as $id) {
                $parameters[] = (string)$id;
                $types[] = ParameterType::STRING;
            }

            $effectedRows = $this->connection->executeStatement(
                sprintf(
                    'UPDATE %s SET %s WHERE id IN (%s)',
                    $this->tableName,
                    implode(', ', $assignments),
                    implode(', ', array_fill(0, count($ids), '?')),
                ),
                $parameters,
                $types,
            );

            if ($effectedRows === count($ids)) {
                continue;
            }

            // mysql and mariadb count the changed rows, not the matched ones, so check which subscriptions exist
            $existing = $this->connection->fetchFirstColumn(
                sprintf('SELECT id FROM %s WHERE id IN (%s)', $this->tableName, implode(', ', array_fill(0, count($ids), '?'))),
                array_map(static fn (int|string $id): string => (string)$id, $ids),
            );

            foreach ($ids as $id) {
                if (!in_array((string)$id, $existing, true)) {
                    throw new SubscriptionNotFound((string)$id);
                }
            }
        }
    }

    /**
     * The values of all columns, last_saved_at is converted by its type.
     *
     * @return array{
     *     id: string,
     *     group_name: string,
     *     run_mode: string,
     *     status: string,
     *     position: int|null,
     *     error_message: string|null,
     *     error_previous_status: string|null,
     *     error_context: string|null,
     *     retry_attempt: int,
     *     last_saved_at: DateTimeImmutable|null,
     *     cleanup_tasks: string|null,
     *     subscriber_id: string,
     *     partition_key: string|null,
     * }
     */
    private function row(Subscription $subscription): array
    {
        $subscriptionError = $subscription->subscriptionError();

        return [
            'id' => $subscription->id(),
            'group_name' => $subscription->group(),
            'run_mode' => $subscription->runMode()->value,
            'status' => $subscription->status()->value,
            'position' => $subscription->position(),
            'error_message' => $subscriptionError?->errorMessage,
            'error_previous_status' => $subscriptionError?->previousStatus?->value,
            'error_context' => $subscriptionError?->errorContext !== null ? json_encode($subscriptionError->errorContext, JSON_THROW_ON_ERROR) : null,
            'retry_attempt' => $subscription->retryAttempt(),
            'last_saved_at' => $subscription->lastSavedAt(),
            'cleanup_tasks' => $subscription->cleanupTasks() !== null ? serialize($subscription->cleanupTasks()) : null,
            'subscriber_id' => $subscription->subscriberId(),
            'partition_key' => $subscription->partitionKey(),
        ];
    }

    /** @param array<string, non-empty-list<int>> $indices */
    public function addToInbox(array $indices): void
    {
        $rows = [];

        foreach ($indices as $subscriptionId => $messageIndices) {
            foreach ($messageIndices as $messageIndex) {
                $rows[] = [$subscriptionId, $messageIndex];
            }
        }

        foreach (array_chunk($rows, self::INBOX_CHUNK) as $chunk) {
            $this->connection->executeStatement(
                sprintf(
                    'INSERT INTO %s (subscription_id, message_index) VALUES %s',
                    $this->inboxTableName,
                    implode(', ', array_fill(0, count($chunk), '(?, ?)')),
                ),
                array_merge(...$chunk),
                array_merge(...array_fill(0, count($chunk), [ParameterType::STRING, ParameterType::INTEGER])),
            );
        }
    }

    /**
     * @param array<string, int> $afterIndex
     * @param positive-int       $limit
     *
     * @return array<string, non-empty-list<int>>
     */
    public function inbox(array $afterIndex, int $limit): array
    {
        $inbox = [];
        $isPostgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;

        foreach (array_chunk($afterIndex, self::INBOX_UNION_CHUNK, true) as $chunk) {
            $parameters = [];
            $types = [];

            foreach ($chunk as $subscriptionId => $index) {
                $parameters[] = (string)$subscriptionId;
                $parameters[] = $index;
                $types[] = ParameterType::STRING;
                $types[] = ParameterType::INTEGER;
            }

            // every partition reads only up to its limit, no matter how many messages wait in its inbox
            $sql = $isPostgres ? $this->lateralInboxQuery(count($chunk), $limit) : $this->unionInboxQuery(count($chunk), $limit);

            /** @var list<array{0: string, 1: int|string}> $rows */
            $rows = $this->connection->fetchAllNumeric($sql, $parameters, $types);

            foreach ($rows as [$subscriptionId, $messageIndex]) {
                $inbox[(string)$subscriptionId][] = (int)$messageIndex;
            }
        }

        // the order of the rows across the partitions is not guaranteed
        foreach ($inbox as &$indices) {
            sort($indices);
        }

        unset($indices);

        return $inbox;
    }

    /**
     * PostgreSQL plans a lateral join once, a union of many queries would cost more than reading the inbox.
     *
     * @param positive-int $partitions
     * @param positive-int $limit
     */
    private function lateralInboxQuery(int $partitions, int $limit): string
    {
        return sprintf(
            'SELECT waiting.id, inbox.message_index FROM (VALUES %s) AS waiting(id, after_index)
            CROSS JOIN LATERAL (
                SELECT message_index FROM %s
                WHERE subscription_id = waiting.id AND message_index > waiting.after_index
                ORDER BY message_index LIMIT %d
            ) inbox',
            implode(', ', array_fill(0, $partitions, '(CAST(? AS VARCHAR), CAST(? AS INTEGER))')),
            $this->inboxTableName,
            $limit,
        );
    }

    /**
     * MySQL and MariaDB do not limit a lateral join early enough or do not support it, neither does SQLite.
     *
     * @param positive-int $partitions
     * @param positive-int $limit
     */
    private function unionInboxQuery(int $partitions, int $limit): string
    {
        $selects = [];

        for ($i = 0; $i < $partitions; $i++) {
            // the derived table allows the limit in every part of the union
            $selects[] = sprintf(
                'SELECT subscription_id, message_index FROM (SELECT subscription_id, message_index FROM %s WHERE subscription_id = ? AND message_index > ? ORDER BY message_index LIMIT %d) inbox_%d',
                $this->inboxTableName,
                $limit,
                $i,
            );
        }

        return implode(' UNION ALL ', $selects);
    }

    /**
     * @param positive-int $idleSeconds
     * @param positive-int $limit
     */
    public function removeIdlePartitions(string $subscriberId, int $idleSeconds, int $limit): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('id')
            ->from($this->tableName)
            ->where('subscriber_id = :subscriber_id')
            ->andWhere('partition_key IS NOT NULL')
            ->andWhere('status = :status')
            ->andWhere('last_saved_at < :idle_since')
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT 1 FROM %s WHERE %s.subscription_id = %s.id)',
                $this->inboxTableName,
                $this->inboxTableName,
                $this->tableName,
            ))
            ->setParameter('subscriber_id', $subscriberId)
            ->setParameter('status', Status::Active->value)
            ->setParameter(
                'idle_since',
                $this->clock->now()->modify(sprintf('-%d seconds', $idleSeconds)),
                Types::DATETIME_IMMUTABLE,
            )
            ->orderBy('id')
            ->setMaxResults($limit);

        // a partition a worker is processing right now is not idle
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $qb->forUpdate(ConflictResolutionMode::SKIP_LOCKED);
        }

        /** @var list<string> $ids */
        $ids = $qb->fetchFirstColumn();

        if ($ids === []) {
            return 0;
        }

        $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE id IN (?)', $this->tableName),
            [$ids],
            [ArrayParameterType::STRING],
        );

        return count($ids);
    }

    /** @param array<string, int> $toIndex */
    private function removeFromInbox(array $toIndex): void
    {
        foreach (array_chunk($toIndex, self::INBOX_CHUNK, true) as $chunk) {
            $conditions = [];
            $parameters = [];
            $types = [];

            foreach ($chunk as $subscriptionId => $index) {
                $conditions[] = '(subscription_id = ? AND message_index <= ?)';
                $parameters[] = (string)$subscriptionId;
                $parameters[] = $index;
                $types[] = ParameterType::STRING;
                $types[] = ParameterType::INTEGER;
            }

            // a plain read, the rows up to the position already exist
            /** @var list<array{0: string, 1: int|string}> $rows */
            $rows = $this->connection->fetchAllNumeric(
                sprintf('SELECT subscription_id, message_index FROM %s WHERE %s', $this->inboxTableName, implode(' OR ', $conditions)),
                $parameters,
                $types,
            );

            $this->deleteFromInbox($rows);
        }
    }

    /**
     * Deletes exactly these rows. A range delete would also lock the next row after the range, often a message the main
     * subscription has just routed, and MariaDB with snapshot isolation (default since 11.6) fails on that row with
     * "Record has changed since last read".
     *
     * @param list<array{0: string, 1: int|string}> $rows
     */
    private function deleteFromInbox(array $rows): void
    {
        /** @var array<string, list<int>> $indices */
        $indices = [];

        foreach ($rows as [$subscriptionId, $messageIndex]) {
            $indices[(string)$subscriptionId][] = (int)$messageIndex;
        }

        $conditions = [];
        $parameters = [];
        $types = [];

        foreach ($indices as $subscriptionId => $messageIndices) {
            foreach (array_chunk($messageIndices, self::INBOX_CHUNK) as $indexChunk) {
                $conditions[] = sprintf(
                    '(subscription_id = ? AND message_index IN (%s))',
                    implode(', ', array_fill(0, count($indexChunk), '?')),
                );
                $parameters[] = (string)$subscriptionId;
                $types[] = ParameterType::STRING;

                foreach ($indexChunk as $messageIndex) {
                    $parameters[] = $messageIndex;
                    $types[] = ParameterType::INTEGER;
                }

                if (count($parameters) < self::INBOX_CHUNK) {
                    continue;
                }

                $this->executeInboxDelete($conditions, $parameters, $types);
                $conditions = [];
                $parameters = [];
                $types = [];
            }
        }

        if ($conditions === []) {
            return;
        }

        $this->executeInboxDelete($conditions, $parameters, $types);
    }

    /**
     * @param non-empty-list<string> $conditions
     * @param list<int|string>       $parameters
     * @param list<ParameterType>    $types
     */
    private function executeInboxDelete(array $conditions, array $parameters, array $types): void
    {
        $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE %s', $this->inboxTableName, implode(' OR ', $conditions)),
            $parameters,
            $types,
        );
    }

    /**
     * @param Closure():T $closure
     *
     * @return T
     *
     * @throws TransactionCommitNotPossible
     *
     * @template T
     */
    public function inLock(Closure $closure): mixed
    {
        $this->connection->beginTransaction();

        try {
            return $closure();
        } catch (TransactionCommitNotPossible $e) {
            throw $e;
        } catch (RetryableException $e) {
            throw new TransactionCommitNotPossible($e);
        } finally {
            try {
                $this->connection->commit();
            } catch (DriverException $e) {
                throw new TransactionCommitNotPossible($e);
            }
        }
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        if (!DoctrineHelper::sameDatabase($this->connection, $connection)) {
            return;
        }

        $table = $schema->createTable($this->tableName);

        $table->addColumn('id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true)
            ->setPlatformOptions($this->caseSensitive($connection));
        $table->addColumn('group_name', Types::STRING)
            ->setLength(32)
            ->setNotnull(true);
        $table->addColumn('run_mode', Types::STRING)
            ->setLength(16)
            ->setNotnull(true);
        $table->addColumn('position', Types::INTEGER)
            ->setNotnull(false);
        $table->addColumn('status', Types::STRING)
            ->setLength(32)
            ->setNotnull(true);
        $table->addColumn('error_message', Types::TEXT)
            ->setNotnull(false);
        $table->addColumn('error_previous_status', Types::STRING)
            ->setLength(32)
            ->setNotnull(false);
        $table->addColumn('error_context', Types::JSON)
            ->setNotnull(false);
        $table->addColumn('retry_attempt', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('last_saved_at', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('cleanup_tasks', Types::TEXT)
            ->setNotnull(false);

        $table->addColumn('subscriber_id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true)
            ->setPlatformOptions($this->caseSensitive($connection));
        $table->addColumn('partition_key', Types::STRING)
            ->setLength(255)
            ->setNotnull(false)
            ->setPlatformOptions($this->caseSensitive($connection));

        $table->setPrimaryKey(['id']);
        $table->addIndex(['group_name']);
        $table->addIndex(['status']);
        // also finds the idle partitions of a subscriber
        $table->addIndex(['subscriber_id', 'last_saved_at']);

        $inbox = $schema->createTable($this->inboxTableName);

        $inbox->addColumn('subscription_id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true)
            ->setPlatformOptions($this->caseSensitive($connection));
        $inbox->addColumn('message_index', Types::INTEGER)
            ->setNotnull(true);

        $inbox->setPrimaryKey(['subscription_id', 'message_index']);
    }

    /**
     * Partition keys come from the messages, keys that only differ in case or accents are different partitions.
     * The default collations of MySQL and MariaDB compare them as equal, so the ids are compared binary there.
     *
     * @return array{charset?: non-empty-string, collation?: non-empty-string}
     */
    private function caseSensitive(Connection $connection): array
    {
        if (!$connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return [];
        }

        return ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'];
    }

    /** @param Data $row */
    private function createSubscription(array $row): Subscription
    {
        $context = $row['error_context'] !== null ?
            json_decode($row['error_context'], true, 512, JSON_THROW_ON_ERROR) : null;

        return new Subscription(
            $row['id'],
            $row['group_name'],
            RunMode::from($row['run_mode']),
            Status::from($row['status']),
            $row['position'],
            $row['error_message'] !== null ? new SubscriptionError(
                $row['error_message'],
                $row['error_previous_status'] !== null ? Status::from($row['error_previous_status']) : Status::New,
                $context,
            ) : null,
            $row['retry_attempt'],
            self::normalizeDateTime($row['last_saved_at'], $this->connection->getDatabasePlatform()),
            $row['cleanup_tasks'] !== null ? unserialize($row['cleanup_tasks']) : null,
            $row['partition_key'],
        );
    }

    /**
     * Everything but the error context and the cleanup tasks is set right away. If there is an error context or there
     * are cleanup tasks, they are loaded with one query when they are accessed (native lazy ghosts). Saving the
     * subscription reads them, so nothing gets lost. Without them the subscription is a plain object.
     *
     * @param LightData $row
     */
    private function createLazySubscription(array $row): Subscription
    {
        $details = $this->detailsLoader($row['id']);

        $error = null;

        if ($row['error_message'] !== null) {
            $previousStatus = $row['error_previous_status'] !== null ? Status::from($row['error_previous_status']) : Status::New;

            if ((int)$row['has_error_context'] === 1) {
                $errorReflector = new ReflectionClass(SubscriptionError::class);
                $error = $errorReflector->newLazyGhost(static function (SubscriptionError $ghost) use ($errorReflector, $details): void {
                    $errorReflector->getProperty('errorContext')->setValue($ghost, $details()['error_context']);
                });

                $errorReflector->getProperty('errorMessage')->setRawValueWithoutLazyInitialization($error, $row['error_message']);
                $errorReflector->getProperty('previousStatus')->setRawValueWithoutLazyInitialization($error, $previousStatus);
            } else {
                $error = new SubscriptionError($row['error_message'], $previousStatus);
            }
        }

        $values = [
            'id' => $row['id'],
            'group' => $row['group_name'],
            'runMode' => RunMode::from($row['run_mode']),
            'status' => Status::from($row['status']),
            'position' => $row['position'] !== null ? (int)$row['position'] : null,
            'error' => $error,
            'retryAttempt' => (int)$row['retry_attempt'],
            'lastSavedAt' => self::normalizeDateTime($row['last_saved_at'], $this->connection->getDatabasePlatform()),
            'partitionKey' => $row['partition_key'],
        ];

        if ((int)$row['has_cleanup_tasks'] === 0) {
            return new Subscription(...$values);
        }

        $reflector = new ReflectionClass(Subscription::class);
        $subscription = $reflector->newLazyGhost(static function (Subscription $ghost) use ($reflector, $details): void {
            $reflector->getProperty('cleanupTasks')->setValue($ghost, $details()['cleanup_tasks']);
        });

        foreach ($values as $property => $value) {
            $reflector->getProperty($property)->setRawValueWithoutLazyInitialization($subscription, $value);
        }

        return $subscription;
    }

    /** @return Closure(): array{error_context: list<Context>|null, cleanup_tasks: list<object>|null} */
    private function detailsLoader(string $id): Closure
    {
        /** @var array{error_context: list<Context>|null, cleanup_tasks: list<object>|null}|null $details */
        $details = null;

        return function () use ($id, &$details): array {
            if ($details !== null) {
                /** @var array{error_context: list<Context>|null, cleanup_tasks: list<object>|null} $loaded */
                $loaded = $details;

                return $loaded;
            }

            /** @var array{error_context: string|null, cleanup_tasks: string|null}|false $row */
            $row = $this->connection->fetchAssociative(
                sprintf('SELECT error_context, cleanup_tasks FROM %s WHERE id = ?', $this->tableName),
                [$id],
            );

            if ($row === false) {
                throw new SubscriptionNotFound($id);
            }

            /** @var list<Context>|null $context */
            $context = $row['error_context'] !== null
                ? json_decode($row['error_context'], true, 512, JSON_THROW_ON_ERROR)
                : null;

            /** @var list<object>|null $cleanupTasks */
            $cleanupTasks = $row['cleanup_tasks'] !== null ? unserialize($row['cleanup_tasks']) : null;

            return $details = ['error_context' => $context, 'cleanup_tasks' => $cleanupTasks];
        };
    }

    private static function normalizeDateTime(mixed $value, AbstractPlatform $platform): DateTimeImmutable
    {
        $normalizedValue = Type::getType(Types::DATETIMETZ_IMMUTABLE)->convertToPHPValue($value, $platform);

        assert($normalizedValue instanceof DateTimeImmutable);

        return $normalizedValue;
    }
}
