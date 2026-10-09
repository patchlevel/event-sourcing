<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionRemoveCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SubscriptionRemoveCommand::class)]
final class SubscriptionRemoveCommandTest extends TestCase
{
    public function testRemoveWithForce(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Remove(null, null))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionRemoveCommand($engine));
        $commandTester->execute(['--force' => true]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testRemoveWithIds(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Remove(['foo'], null))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionRemoveCommand($engine));
        $commandTester->execute(['--id' => ['foo']]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testRemoveConfirmed(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Remove(null, null))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionRemoveCommand($engine));
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testRemoveAborted(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->never())
            ->method('execute');

        $commandTester = new CommandTester(new SubscriptionRemoveCommand($engine));
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        self::assertSame(1, $commandTester->getStatusCode());
    }
}
