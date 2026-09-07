<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use LogicException;
use Patchlevel\EventSourcing\Console\Command\SubscriptionBootCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\ReturnCallback;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SubscriptionBootCommand::class)]
final class SubscriptionBootCommandTest extends TestCase
{
    public function testBoot(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(null, null))
            ->willReturn([new Subscription('foo')]);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Boot(['foo'], null, 1000))
            ->willReturn(new ProcessedResult(0, true));

        $commandTester = new CommandTester(new SubscriptionBootCommand($engine));
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testBootWithSetup(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(null, null))
            ->willReturn([new Subscription('foo')]);
        $engine
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(new ReturnCallback([
                [[new Setup(['foo'], null)], new Result()],
                [[new Boot(['foo'], null, 1000)], new ProcessedResult(0, true)],
            ]));

        $commandTester = new CommandTester(new SubscriptionBootCommand($engine));
        $commandTester->execute(['--setup' => true]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testBootNotFinished(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(null, null))
            ->willReturn([new Subscription('foo')]);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Boot(['foo'], null, 1000))
            ->willReturn(new ProcessedResult(0, false));

        $commandTester = new CommandTester(new SubscriptionBootCommand($engine));
        $commandTester->execute(['--run-limit' => 1]);

        self::assertSame(1, $commandTester->getStatusCode());
    }

    public function testBootUnexpectedResult(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(null, null))
            ->willReturn([new Subscription('foo')]);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Boot(['foo'], null, 1000))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionBootCommand($engine));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected ProcessedResult');

        $commandTester->execute([]);
    }
}
