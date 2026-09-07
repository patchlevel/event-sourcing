<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionTeardownCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Teardown;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SubscriptionTeardownCommand::class)]
final class SubscriptionTeardownCommandTest extends TestCase
{
    public function testTeardown(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Teardown(null, null))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionTeardownCommand($engine));
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testTeardownWithFilter(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Teardown(['foo'], ['bar']))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionTeardownCommand($engine));
        $commandTester->execute(['--id' => ['foo'], '--group' => ['bar']]);

        self::assertSame(0, $commandTester->getStatusCode());
    }
}
