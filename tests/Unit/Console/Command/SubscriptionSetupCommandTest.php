<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionSetupCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SubscriptionSetupCommand::class)]
final class SubscriptionSetupCommandTest extends TestCase
{
    public function testSetup(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Setup(null, null, false))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionSetupCommand($engine));
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testSetupWithSkipBooting(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Setup(['foo'], ['bar'], true))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionSetupCommand($engine));
        $commandTester->execute(['--id' => ['foo'], '--group' => ['bar'], '--skip-booting' => true]);

        self::assertSame(0, $commandTester->getStatusCode());
    }
}
