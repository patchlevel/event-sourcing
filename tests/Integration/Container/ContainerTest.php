<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Container;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Container\ConfigBuilder;
use Patchlevel\EventSourcing\Container\DefaultContainer;
use Patchlevel\EventSourcing\Tests\Integration\Container\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Container\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\Container\Projection\ProfileProjection;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class ContainerTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
        SendEmailMock::reset();
    }

    public function testSuccessful(): void
    {
        $config = (new ConfigBuilder())
            ->singleTable()
            ->addAggregatePath(__DIR__ . '/Aggregate')
            ->addEventPath(__DIR__ . '/Events')
            ->addProcessor(SendEmailProcessor::class)
            ->addProjector(ProfileProjection::class)
            ->build();

        $container = new DefaultContainer(
            $config,
            [
                'event_sourcing.connection' => $this->connection,
                ProfileProjection::class => static fn (DefaultContainer $container) => new ProfileProjection($container->connection()),
                SendEmailProcessor::class => static fn () => new SendEmailProcessor(),
            ]
        );

        $repository = $container->repository(Profile::class);
        $container->schemaDirector()->create();
        $container->get(ProfileProjection::class)->create();

        $profile = Profile::create(ProfileId::fromString('1'), 'John');

        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $profile = $repository->load('1');

        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }
}
