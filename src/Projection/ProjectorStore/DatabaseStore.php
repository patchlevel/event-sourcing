<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStatus;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;

use function array_map;

final class DatabaseStore implements ProjectorStore, SchemaConfigurator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectorTable = 'projector'
    ) {
    }

    public function getProjectorState(ProjectorId $projectorId): ProjectorState
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->projectorTable)
            ->where('projector = :projector AND version = :version')
            ->getSQL();

        /** @var array{projector: string, version: int, position: int, status: string}|false $result */
        $result = $this->connection->fetchAssociative($sql, [
            'projector' => $projectorId->name(),
            'version' => $projectorId->version(),
        ]);

        if ($result === false) {
            throw new ProjectorStateNotFound($projectorId);
        }

        return new ProjectorState(
            new ProjectorId($result['projector'], $result['version']),
            ProjectorStatus::from($result['status']),
            $result['position']
        );
    }

    public function getStateFromAllProjectors(): ProjectorStateCollection
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->projectorTable)
            ->getSQL();

        /** @var list<array{projector: string, version: int, position: int, status: string}> $result */
        $result = $this->connection->fetchAllAssociative($sql);

        return new ProjectorStateCollection(
            array_map(
                static function (array $data) {
                    return new ProjectorState(
                        new ProjectorId($data['projector'], $data['version']),
                        ProjectorStatus::from($data['status']),
                        $data['position']
                    );
                },
                $result
            )
        );
    }

    public function saveProjectorState(ProjectorState ...$projectorStates): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($projectorStates): void {
                foreach ($projectorStates as $projectorState) {
                    try {
                        $this->getProjectorState($projectorState->id());
                        $connection->update(
                            $this->projectorTable,
                            [
                                'position' => $projectorState->position(),
                                'status' => $projectorState->status()->value,
                            ],
                            [
                                'projector' => $projectorState->id()->name(),
                                'version' => $projectorState->id()->version(),
                            ]
                        );
                    } catch (ProjectorStateNotFound) {
                        $connection->insert(
                            $this->projectorTable,
                            [
                                'projector' => $projectorState->id()->name(),
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

    public function removeProjectorState(ProjectorId $projectorId): void
    {
        $this->connection->delete($this->projectorTable, [
            'projector' => $projectorId->name(),
            'version' => $projectorId->version(),
        ]);
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        $table = $schema->createTable($this->projectorTable);

        $table->addColumn('projector', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('version', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('position', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('status', Types::STRING)
            ->setNotnull(true);

        $table->setPrimaryKey(['projector', 'version']);
    }
}
