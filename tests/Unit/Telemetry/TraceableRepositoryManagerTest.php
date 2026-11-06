<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Telemetry\TraceableRepository;
use Patchlevel\EventSourcing\Telemetry\TraceableRepositoryManager;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TraceableRepositoryManager::class)]
final class TraceableRepositoryManagerTest extends TestCase
{
    use InMemoryTracer;

    public function testWrapsRepository(): void
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->expects($this->once())
            ->method('get')
            ->with(Profile::class)
            ->willReturn($this->createMock(Repository::class));

        $manager = new TraceableRepositoryManager(
            $repositoryManager,
            new AggregateRootRegistry(['profile' => Profile::class]),
            $this->createTracerProvider(),
        );

        $repository = $manager->get(Profile::class);

        self::assertInstanceOf(TraceableRepository::class, $repository);

        // the inner manager must only be asked once
        self::assertSame($repository, $manager->get(Profile::class));
    }
}
