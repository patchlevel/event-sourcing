<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionRunCommand;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\ReturnCallback;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SubscriptionRunCommand::class)]
final class SubscriptionRunCommandTest extends TestCase
{
    public function testRun(): void
    {
        $store = $this->createMock(Store::class);

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(null, null))
            ->willReturn([new Subscription('foo')]);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Run(['foo'], null, 100))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionRunCommand($engine, $store));
        $commandTester->execute(['--run-limit' => 1, '--sleep' => 0]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testRunWithRebuild(): void
    {
        $store = $this->createMock(Store::class);

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(null, null))
            ->willReturn([new Subscription('foo')]);
        $engine
            ->expects($this->exactly(3))
            ->method('execute')
            ->willReturnCallback(new ReturnCallback([
                [[new Remove(['foo'], null)], new Result()],
                [[new Boot(['foo'], null)], new Result()],
                [[new Run(['foo'], null, 100)], new Result()],
            ]));

        $commandTester = new CommandTester(new SubscriptionRunCommand($engine, $store));
        $commandTester->execute(['--run-limit' => 1, '--sleep' => 0, '--rebuild' => true]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testRunWithSubscriptionStore(): void
    {
        $store = $this->createMockForIntersectionOfInterfaces([Store::class, SubscriptionStore::class]);
        $store
            ->expects($this->once())
            ->method('setupSubscription');
        $store
            ->expects($this->once())
            ->method('supportSubscription')
            ->willReturn(true);
        $store
            ->expects($this->once())
            ->method('wait')
            ->with(0);

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(null, null))
            ->willReturn([new Subscription('foo')]);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with(new Run(['foo'], null, 100))
            ->willReturn(new Result());

        $commandTester = new CommandTester(new SubscriptionRunCommand($engine, $store));
        $commandTester->execute(['--run-limit' => 1, '--sleep' => 0]);

        self::assertSame(0, $commandTester->getStatusCode());
    }
}
