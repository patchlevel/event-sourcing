<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Repository;

use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunSubscriptionEngineRepositoryManager::class)]
final class RunSubscriptionEngineRepositoryManagerTest extends TestCase
{
    public function testGet(): void
    {
        $defaultRepository = $this->createMock(Repository::class);

        $defaultRepositoryManager = $this->createMock(RepositoryManager::class);
        $defaultRepositoryManager->expects($this->once())->method('get')->with(Profile::class)->willReturn($defaultRepository);

        $engine = $this->createMock(SubscriptionEngine::class);

        $repository = new RunSubscriptionEngineRepositoryManager(
            $defaultRepositoryManager,
            $engine,
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        $repository->get(Profile::class);
    }
}
