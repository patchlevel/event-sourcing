<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootClassNotRegistered;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Repository\DefaultRepositoryManager */
final class DefaultRepositoryManagerTest extends TestCase
{
    use ProphecyTrait;

    public function testGetNewRepository(): void
    {
        $store = $this->prophesize(Store::class);
        $eventBus = $this->prophesize(EventBus::class);

        $repositoryManager = new DefaultRepositoryManager(
            new AggregateRootRegistry([
                'profile' => Profile::class,
                'profile2' => ProfileWithSnapshot::class,
            ]),
            $store->reveal(),
            $eventBus->reveal()
        );

        $repository1 = $repositoryManager->get(Profile::class);
        $repository2 = $repositoryManager->get(ProfileWithSnapshot::class);

        self::assertNotEquals($repository1, $repository2);
    }

    public function testSameRepository(): void
    {
        $store = $this->prophesize(Store::class);
        $eventBus = $this->prophesize(EventBus::class);

        $repositoryManager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store->reveal(),
            $eventBus->reveal()
        );

        $repository1 = $repositoryManager->get(Profile::class);
        $repository2 = $repositoryManager->get(Profile::class);

        self::assertSame($repository1, $repository2);
    }

    public function testNotDefined(): void
    {
        $this->expectException(AggregateRootClassNotRegistered::class);

        $store = $this->prophesize(Store::class);
        $eventBus = $this->prophesize(EventBus::class);

        $repositoryManager = new DefaultRepositoryManager(
            new AggregateRootRegistry([]),
            $store->reveal(),
            $eventBus->reveal()
        );

        $repositoryManager->get(Profile::class);
    }
}
