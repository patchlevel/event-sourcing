<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Repository;

use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager */
final class RunSubscriptionEngineRepositoryManagerTest extends TestCase
{
    use ProphecyTrait;

    public function testGet(): void
    {
        $defaultRepository = $this->prophesize(Repository::class)->reveal();

        $defaultRepositoryManager = $this->prophesize(RepositoryManager::class);
        $defaultRepositoryManager->get(Profile::class)->willReturn($defaultRepository)->shouldBeCalledOnce();

        $engine = $this->prophesize(SubscriptionEngine::class);

        $repository = new RunSubscriptionEngineRepositoryManager(
            $defaultRepositoryManager->reveal(),
            $engine->reveal(),
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        $repository->get(Profile::class);
    }
}
