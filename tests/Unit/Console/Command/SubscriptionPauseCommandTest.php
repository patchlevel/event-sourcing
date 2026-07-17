<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionPauseCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SubscriptionPauseCommand::class)]
final class SubscriptionPauseCommandTest extends TestCase
{
    public function testPause(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Pause(null, null))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionPauseCommand($engine));
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testPauseWithFilter(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Pause(['foo'], ['bar']))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionPauseCommand($engine));
        $commandTester->execute(['--id' => ['foo'], '--group' => ['bar']]);

        self::assertSame(0, $commandTester->getStatusCode());
    }
}
