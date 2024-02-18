<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Projection\Projection\Store\DoctrineStore;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection\ProfileProjector;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
final class ProjectionistTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testRun(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            'eventstore',
        );

        $projectionStore = new DoctrineStore($this->connection);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            DefaultEventBus::create(),
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainSchemaConfigurator([
                $store,
                $projectionStore,
            ]),
        );

        $schemaDirector->create();

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $projectionist = new DefaultProjectionist(
            $store,
            $projectionStore,
            [new ProfileProjector($this->connection)],
        );

        $projectionist->boot();
        $projectionist->run();

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile_1 WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $projectionist->remove();
    }
}
