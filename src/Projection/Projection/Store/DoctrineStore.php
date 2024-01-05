<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionError;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;

use function array_map;

/** @psalm-type Data = array{
 *     name: string,
 *     version: int,
 *     position: int,
 *     status: string,
 *     error_message: string|null,
 *     error_object: string|null,
 *     retry: int,
 * }
 */
final class DoctrineStore implements ProjectionStore, SchemaConfigurator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectionTable = 'projections',
    ) {
    }

    public function get(ProjectionId $projectionId): Projection
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->projectionTable)
            ->where('name = :name AND version = :version')
            ->getSQL();

        /** @var Data|false $result */
        $result = $this->connection->fetchAssociative($sql, [
            'name' => $projectionId->name(),
            'version' => $projectionId->version(),
        ]);

        if ($result === false) {
            throw new ProjectionNotFound($projectionId);
        }

        return $this->createProjection($result);
    }

    public function all(): ProjectionCollection
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->projectionTable)
            ->getSQL();

        /** @var list<Data> $result */
        $result = $this->connection->fetchAllAssociative($sql);

        return new ProjectionCollection(
            array_map(
                fn (array $data) => $this->createProjection($data),
                $result,
            ),
        );
    }

    /** @param Data $row */
    private function createProjection(array $row): Projection
    {
        return new Projection(
            new ProjectionId($row['name'], $row['version']),
            ProjectionStatus::from($row['status']),
            $row['position'],
            $row['error_message'] ? new ProjectionError(
                $row['error_message'],
                ErrorSerializer::unserialize($row['error_object']),
            ) : null,
            $row['retry'],
        );
    }

    public function save(Projection ...$projections): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($projections): void {
                foreach ($projections as $projection) {
                    $errorObject = ErrorSerializer::serialize($projection->projectionError()?->errorObject);

                    try {
                        $effectedRows = (int)$connection->update(
                            $this->projectionTable,
                            [
                                'position' => $projection->position(),
                                'status' => $projection->status()->value,
                                'error_message' => $projection->projectionError()?->errorMessage,
                                'error_object' => $errorObject,
                                'retry' => $projection->retry(),
                            ],
                            [
                                'name' => $projection->id()->name(),
                                'version' => $projection->id()->version(),
                            ],
                        );

                        if ($effectedRows === 0) {
                            $this->get($projection->id()); // check if projection exists, otherwise throw ProjectionNotFound
                        }
                    } catch (ProjectionNotFound) {
                        $connection->insert(
                            $this->projectionTable,
                            [
                                'name' => $projection->id()->name(),
                                'version' => $projection->id()->version(),
                                'position' => $projection->position(),
                                'status' => $projection->status()->value,
                                'error_message' => $projection->projectionError()?->errorMessage,
                                'error_object' => $errorObject,
                                'retry' => $projection->retry(),
                            ],
                        );
                    }
                }
            },
        );
    }

    public function remove(ProjectionId ...$projectionIds): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($projectionIds): void {
                foreach ($projectionIds as $projectionId) {
                    $connection->delete($this->projectionTable, [
                        'name' => $projectionId->name(),
                        'version' => $projectionId->version(),
                    ]);
                }
            },
        );
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        $table = $schema->createTable($this->projectionTable);

        $table->addColumn('name', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('version', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('position', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('status', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('error_message', Types::STRING)
            ->setNotnull(false);
        $table->addColumn('error_object', Types::BLOB)
            ->setNotnull(false);
        $table->addColumn('retry', Types::INTEGER)
            ->setNotnull(true)
            ->setDefault(0);

        $table->setPrimaryKey(['name', 'version']);
    }
}
