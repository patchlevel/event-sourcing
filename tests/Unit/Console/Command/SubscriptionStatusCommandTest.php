<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionStatusCommand;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SubscriptionStatusCommand::class)]
final class SubscriptionStatusCommandTest extends TestCase
{
    public function testStatusList(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->willReturn([
                new Subscription('foo'),
                new Subscription('bar', 'other', RunMode::Once, Status::Active, 42),
            ]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('foo', $display);
        self::assertStringContainsString('bar', $display);
        self::assertStringContainsString('active', $display);
        self::assertStringContainsString('42', $display);
    }

    public function testStatusDetail(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->willReturn([
                new Subscription('foo', 'default', RunMode::FromBeginning, Status::Active, 42),
            ]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute(['id' => 'foo']);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('foo', $display);
        self::assertStringContainsString('active', $display);
        self::assertStringContainsString('42', $display);
    }

    public function testStatusDetailWithError(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->willReturn([
                new Subscription(
                    'foo',
                    'default',
                    RunMode::FromBeginning,
                    Status::Error,
                    42,
                    SubscriptionError::fromThrowable(
                        Status::Active,
                        new RuntimeException('something went wrong'),
                    ),
                ),
            ]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute(['id' => 'foo']);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('foo', $display);
        self::assertStringContainsString('error', $display);
        self::assertStringContainsString('something went wrong', $display);
    }

    public function testStatusNotFound(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->willReturn([new Subscription('foo')]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));

        $this->expectException(SubscriptionNotFound::class);

        $commandTester->execute(['id' => 'bar']);
    }
}
