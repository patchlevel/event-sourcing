<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Closure;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionError;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;

use function array_map;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @psalm-type Data = array{
 *     id: string,
 *     group_name: string,
 *     position: int,
 *     status: string,
 *     error_message: string|null,
 *     error_context: string|null,
 *     retry: int,
 * }
 */
final class DoctrineStore implements LockableProjectionStore, SchemaConfigurator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectionTable = 'projections',
    ) {
    }

    public function get(string $projectionId): Projection
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->projectionTable)
            ->where('id = :id')
            ->getSQL();

        /** @var Data|false $result */
        $result = $this->connection->fetchAssociative($sql, ['id' => $projectionId]);

        if ($result === false) {
            throw new ProjectionNotFound($projectionId);
        }

        return $this->createProjection($result);
    }

    /** @return list<Projection> */
    public function find(ProjectionCriteria|null $criteria = null): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->projectionTable);

        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $qb->forUpdate();
        }

        if ($criteria !== null) {
            if ($criteria->ids !== null) {
                $qb->andWhere('id IN (:ids)')
                    ->setParameter(
                        'ids',
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
                        array_map(static fn (ProjectionStatus $status) => $status->value, $criteria->status),
                        ArrayParameterType::STRING,
                    );
            }
        }

        /** @var list<Data> $result */
        $result = $qb->fetchAllAssociative();

        return array_map(
            fn (array $data) => $this->createProjection($data),
            $result,
        );
    }

    public function add(Projection $projection): void
    {
        $projectionError = $projection->projectionError();

        $this->connection->insert(
            $this->projectionTable,
            [
                'id' => $projection->id(),
                'group_name' => $projection->group(),
                'position' => $projection->position(),
                'status' => $projection->status()->value,
                'error_message' => $projectionError?->errorMessage,
                'error_context' => $projectionError?->errorContext !== null ? json_encode($projectionError->errorContext, JSON_THROW_ON_ERROR) : null,
                'retry' => $projection->retry(),
            ],
        );
    }

    public function update(Projection $projection): void
    {
        $projectionError = $projection->projectionError();

        $effectedRows = $this->connection->update(
            $this->projectionTable,
            [
                'group_name' => $projection->group(),
                'position' => $projection->position(),
                'status' => $projection->status()->value,
                'error_message' => $projectionError?->errorMessage,
                'error_context' => $projectionError?->errorContext !== null ? json_encode($projectionError->errorContext, JSON_THROW_ON_ERROR) : null,
                'retry' => $projection->retry(),
            ],
            [
                'id' => $projection->id(),
            ],
        );

        if ($effectedRows === 0) {
            throw new ProjectionNotFound($projection->id());
        }
    }

    public function remove(Projection $projection): void
    {
        $this->connection->delete($this->projectionTable, ['id' => $projection->id()]);
    }

    public function inLock(Closure $closure): void
    {
        $this->connection->beginTransaction();

        try {
            $closure();
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
        $table = $schema->createTable($this->projectionTable);

        $table->addColumn('id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('group_name', Types::STRING)
            ->setLength(32)
            ->setNotnull(true);
        $table->addColumn('position', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('status', Types::STRING)
            ->setLength(32)
            ->setNotnull(true);
        $table->addColumn('error_message', Types::STRING)
            ->setLength(255)
            ->setNotnull(false);
        $table->addColumn('error_context', Types::JSON)
            ->setNotnull(false);
        $table->addColumn('retry', Types::INTEGER)
            ->setNotnull(true)
            ->setDefault(0);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['group_name']);
        $table->addIndex(['status']);
    }

    /** @param Data $row */
    private function createProjection(array $row): Projection
    {
        $context = $row['error_context'] !== null ?
            json_decode($row['error_context'], true, 512, JSON_THROW_ON_ERROR) : null;

        return new Projection(
            $row['id'],
            $row['group_name'],
            ProjectionStatus::from($row['status']),
            $row['position'],
            $row['error_message'] !== null ? new ProjectionError(
                $row['error_message'],
                $context,
            ) : null,
            $row['retry'],
        );
    }
}
