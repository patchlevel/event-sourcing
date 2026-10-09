<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionReactivateCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Reactivate;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SubscriptionReactivateCommand::class)]
final class SubscriptionReactivateCommandTest extends TestCase
{
    public function testReactivate(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Reactivate(null, null))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionReactivateCommand($engine));
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testReactivateWithFilter(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Reactivate(['foo'], ['bar']))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionReactivateCommand($engine));
        $commandTester->execute(['--id' => ['foo'], '--group' => ['bar']]);

        self::assertSame(0, $commandTester->getStatusCode());
    }
}
