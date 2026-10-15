<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionStatusCommand;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\InboxStats;
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
        $engine = $this->engine([
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
        $engine = $this->engine([
            new Subscription('foo', 'projector', RunMode::FromBeginning, Status::Active, 42),
            new Subscription('baz', 'projector', RunMode::FromBeginning, Status::Active, 7),
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
        self::assertStringNotContainsString('baz', $display);
        self::assertEquals(
            new SubscriptionEngineCriteria(['foo', 'bar'], ['projector'], partitions: false),
            $engine->criteria[0],
        );
    }

    public function testPartitionSummaryWithoutLoadingThePartitions(): void
    {
        $main = new Subscription('profile', status: Status::Active, position: 10);
        $broken = Subscription::partition($main, 'broken', 3);
        $broken->error('database down');

        $engine = $this->engine([
            $main,
            Subscription::partition($main, 'a', 5),
            Subscription::partition($main, 'b', 6),
            $broken,
            new Subscription('plain', status: Status::Active, position: 3),
        ]);

        $engine->inbox = new InboxStats(7, 2);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        self::assertStringContainsString('3 (error: 1), inbox: 7 in 2', $display);
        self::assertStringNotContainsString('└', $display, 'the partitions are only listed with --partitions');
        self::assertEquals(
            [new SubscriptionEngineCriteria(partitions: false)],
            $engine->criteria,
            'only the main subscriptions are loaded',
        );
    }

    public function testListAllPartitions(): void
    {
        $main = new Subscription('profile', status: Status::Active, position: 10);
        $broken = Subscription::partition($main, 'broken', 3);
        $broken->error('database down');

        $engine = $this->engine([
            $main,
            Subscription::partition($main, 'a', 5),
            Subscription::partition($main, 'b', 6),
            $broken,
        ]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute(['--partitions' => true]);

        $display = $commandTester->getDisplay();

        self::assertStringContainsString('3 (error: 1)', $display);
        self::assertStringContainsString('└ a', $display);
        self::assertStringContainsString('└ broken', $display);
    }

    public function testStatusDetail(): void
    {
        $main = new Subscription('foo', 'default', RunMode::FromBeginning, Status::Active, 42);
        $engine = $this->engine([$main, Subscription::partition($main, 'a', 1)]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute(['id' => 'foo']);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('foo', $display);
        self::assertStringContainsString('active', $display);
        self::assertStringContainsString('42', $display);
        self::assertEquals([new SubscriptionEngineCriteria(['foo'], partitions: false)], $engine->criteria, 'partitions are not loaded');
    }

    public function testStatusDetailOfAPartition(): void
    {
        $main = new Subscription('foo', 'default', RunMode::FromBeginning, Status::Active, 42);
        $engine = $this->engine([$main, Subscription::partition($main, 'tenant-a', 7)]);

        $commandTester = new CommandTester(new SubscriptionStatusCommand($engine));
        $commandTester->execute(['id' => 'foo#tenant-a']);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('foo#tenant-a', $display);
        self::assertStringContainsString('7', $display);
    }

    public function testStatusDetailWithError(): void
    {
        $engine = $this->engine([
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
        $commandTester = new CommandTester(new SubscriptionStatusCommand($this->engine([])));

        $this->expectException(SubscriptionNotFound::class);

        $commandTester->execute(['id' => 'bar']);
    }

    /**
     * Filters like the subscription store and records every criteria it gets.
     *
     * @param list<Subscription> $subscriptions
     */
    private function engine(array $subscriptions): FakeSubscriptionEngine
    {
        return new FakeSubscriptionEngine($subscriptions);
    }
}
