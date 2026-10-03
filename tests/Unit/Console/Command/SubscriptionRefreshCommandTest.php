<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionRefreshCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Refresh;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SubscriptionRefreshCommand::class)]
final class SubscriptionRefreshCommandTest extends TestCase
{
    public function testRefresh(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Refresh(null, null))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionRefreshCommand($engine));
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testRefreshWithFilter(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Refresh(['foo'], ['bar']))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionRefreshCommand($engine));
        $commandTester->execute(['--id' => ['foo'], '--group' => ['bar']]);

        self::assertSame(0, $commandTester->getStatusCode());
    }
}
