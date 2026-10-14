<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionStatusCommand;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
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
            ->with(new SubscriptionEngineCriteria())
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

    public function testStatusListFiltered(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(['foo', 'bar'], ['projector']))
            ->willReturn([
                new Subscription('foo', 'projector', RunMode::FromBeginning, Status::Active, 42),
            ]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute([
            '--id' => ['foo', 'bar'],
            '--group' => ['projector'],
        ]);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('foo', $display);
        self::assertStringContainsString('projector', $display);
    }

    public function testStatusDetail(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(['foo']))
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
            ->with(new SubscriptionEngineCriteria(['foo']))
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
            ->with(new SubscriptionEngineCriteria(['bar']))
            ->willReturn([]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));

        $this->expectException(SubscriptionNotFound::class);

        $commandTester->execute(['id' => 'bar']);
    }

    public function testStatusListWithPartitions(): void
    {
        $root = new Subscription('foo', status: Status::Active, position: 10);
        $failed = Subscription::partition($root, 'tenant-a', 1, 5);
        $failed->error('broken');

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->exactly(2))
            ->method('subscriptions')
            ->willReturn([
                $root,
                Subscription::partition($root, 'tenant-b', 0, 10),
                $failed,
            ]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        self::assertStringContainsString('2 (error: 1)', $display);
        self::assertStringNotContainsString('tenant-a', $display);

        $commandTester->execute(['--partitions' => true]);

        $display = $commandTester->getDisplay();

        self::assertStringContainsString('└ tenant-a', $display);
        self::assertStringContainsString('└ tenant-b', $display);
        self::assertStringContainsString('shard 1', $display);
        self::assertStringContainsString('broken', $display);
    }

    public function testStatusDetailOfPartition(): void
    {
        $root = new Subscription('foo', status: Status::Active, position: 10);
        $partition = Subscription::partition($root, 'tenant-a', null, 5);
        $partition->error('broken');

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(['foo']))
            ->willReturn([$root, $partition]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute(['id' => 'foo', '--partition' => 'tenant-a']);

        $display = $commandTester->getDisplay();

        self::assertStringContainsString('foo#tenant-a', $display);
        self::assertStringContainsString('broken', $display);
    }

    public function testStatusDetailOfUnknownPartition(): void
    {
        $root = new Subscription('foo', status: Status::Active, position: 10);

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine->method('subscriptions')->willReturn([$root]);

        $this->expectException(SubscriptionNotFound::class);
        $this->expectExceptionMessage('Partition "tenant-a" of the subscription with the id "foo" not found.');

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute(['id' => 'foo', '--partition' => 'tenant-a']);
    }
}
