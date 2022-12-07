<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;

use function array_map;

final class DoctrineStore implements ProjectionStore, SchemaConfigurator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectionTable = 'projections'
    ) {
    }

    public function get(ProjectionId $projectionId): Projection
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->projectionTable)
            ->where('name = :name AND version = :version')
            ->getSQL();

        /** @var array{name: string, version: int, position: int, status: string}|false $result */
        $result = $this->connection->fetchAssociative($sql, [
            'name' => $projectionId->name(),
            'version' => $projectionId->version(),
        ]);

        if ($result === false) {
            throw new ProjectionNotFound($projectionId);
        }

        return new Projection(
            new ProjectionId($result['name'], $result['version']),
            ProjectionStatus::from($result['status']),
            $result['position']
        );
    }

    public function all(): ProjectionCollection
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->projectionTable)
            ->getSQL();

        /** @var list<array{name: string, version: int, position: int, status: string}> $result */
        $result = $this->connection->fetchAllAssociative($sql);

        return new ProjectionCollection(
            array_map(
                static function (array $data) {
                    return new Projection(
                        new ProjectionId($data['name'], $data['version']),
                        ProjectionStatus::from($data['status']),
                        $data['position']
                    );
                },
                $result
            )
        );
    }

    public function save(Projection ...$projections): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($projections): void {
                foreach ($projections as $projectorState) {
                    try {
                        $this->get($projectorState->id());
                        $connection->update(
                            $this->projectionTable,
                            [
                                'position' => $projectorState->position(),
                                'status' => $projectorState->status()->value,
                            ],
                            [
                                'name' => $projectorState->id()->name(),
                                'version' => $projectorState->id()->version(),
                            ]
                        );
                    } catch (ProjectionNotFound) {
                        $connection->insert(
                            $this->projectionTable,
                            [
                                'name' => $projectorState->id()->name(),
                                'version' => $projectorState->id()->version(),
                                'position' => $projectorState->position(),
                                'status' => $projectorState->status()->value,
                            ]
                        );
                    }
                }
            }
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
            }
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

        $table->setPrimaryKey(['name', 'version']);
    }
}
