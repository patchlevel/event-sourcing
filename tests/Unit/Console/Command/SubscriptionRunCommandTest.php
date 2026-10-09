<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionRunCommand;
use Patchlevel\EventSourcing\Store\ListenableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\ReturnCallback;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function file_exists;
use function sys_get_temp_dir;
use function tempnam;
use function time;
use function touch;
use function unlink;

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

    public function testRunStopsWhenFinished(): void
    {
        $store = $this->createMock(Store::class);

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->willReturn([new Subscription('foo')]);
        $engine
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                new ProcessedResult(100, false),
                new ProcessedResult(20, true),
            );

        $commandTester = new CommandTester(new SubscriptionRunCommand($engine, $store));
        $commandTester->execute(['--stop-when-finished' => true, '--sleep' => 0]);

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

    public function testRunWithListenableStore(): void
    {
        $store = $this->createMockForIntersectionOfInterfaces([Store::class, ListenableStore::class]);
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

    public function testRunSkipsWaitWhenMessageLimitReached(): void
    {
        $store = $this->createMockForIntersectionOfInterfaces([Store::class, ListenableStore::class]);
        $store
            ->expects($this->never())
            ->method('wait');

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
            ->willReturn(new ProcessedResult(100, false));

        $commandTester = new CommandTester(new SubscriptionRunCommand($engine, $store));
        $commandTester->execute(['--run-limit' => 1, '--sleep' => 0]);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testRunStopsOnRestartSignal(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'restart');
        touch($file, time() + 60);

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
            ->willReturn(new ProcessedResult(0, true));

        $commandTester = new CommandTester(new SubscriptionRunCommand($engine, $store));
        $commandTester->execute(['--run-limit' => 3, '--sleep' => 0, '--restart-signal-file' => $file]);

        unlink($file);

        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testRunWithHeartbeat(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'heartbeat');

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
            ->willReturn(new ProcessedResult(0, true));

        $commandTester = new CommandTester(new SubscriptionRunCommand($engine, $store));
        $commandTester->execute(['--run-limit' => 1, '--sleep' => 0, '--heartbeat-file' => $file]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertFalse(file_exists($file));
    }
}
