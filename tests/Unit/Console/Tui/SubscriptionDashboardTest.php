<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Tui;

use Patchlevel\EventSourcing\Console\Tui\SubscriptionDashboard;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Tui\Tui;

use function class_exists;

#[CoversClass(SubscriptionDashboard::class)]
final class SubscriptionDashboardTest extends TestCase
{
    private const DOWN = "\x1b[B";
    private const ENTER = "\r";
    private const CTRL_D = "\x04";

    protected function setUp(): void
    {
        if (class_exists(Tui::class)) {
            return;
        }

        self::markTestSkipped('symfony/tui is not installed');
    }

    public function testShowSubscriptions(): void
    {
        $engine = new RecordingSubscriptionEngine([
            new Subscription('profile', 'projector', RunMode::FromBeginning, Status::Active, 2),
            new Subscription('welcome_mail', 'processor', RunMode::FromNow, Status::Paused, 1),
        ]);

        $screen = $this->runDashboard($engine, []);

        self::assertStringContainsString('profile', $screen);
        self::assertStringContainsString('welcome_mail', $screen);
        self::assertStringContainsString('● active', $screen);
        self::assertStringContainsString('‖ paused', $screen);
        self::assertStringContainsString('head 3', $screen);
    }

    public function testPauseSelectedSubscription(): void
    {
        $engine = new RecordingSubscriptionEngine([
            new Subscription('a', status: Status::Active),
            new Subscription('b', status: Status::Active),
        ]);

        $screen = $this->runDashboard($engine, [self::DOWN, 'p']);

        self::assertEquals([new Pause(['b'])], $engine->commands);
        self::assertStringContainsString('Pause "b" done', $screen);
    }

    public function testActionNotPossibleForStatus(): void
    {
        $engine = new RecordingSubscriptionEngine([new Subscription('a', status: Status::New)]);

        $screen = $this->runDashboard($engine, ['p']);

        self::assertSame([], $engine->commands);
        self::assertStringContainsString('Pause is not possible for subscription "a" with status "new"', $screen);
    }

    public function testRemoveNeedsConfirmation(): void
    {
        $engine = new RecordingSubscriptionEngine([new Subscription('a', status: Status::Active)]);

        $screen = $this->runDashboard($engine, [self::CTRL_D]);

        self::assertSame([], $engine->commands);
        self::assertStringContainsString('Remove "a"?', $screen);

        $this->runDashboard($engine, [self::CTRL_D, 'y']);

        self::assertEquals([new Remove(['a'])], $engine->commands);
    }

    public function testFilter(): void
    {
        $engine = new RecordingSubscriptionEngine([
            new Subscription('profile', status: Status::Active),
            new Subscription('welcome_mail'),
        ]);

        // typed keys must not trigger actions like "p" (pause) while filtering
        $screen = $this->runDashboard($engine, ['/', 'm', 'a', 'i', 'l', 'p', "\x7f", self::ENTER]);

        self::assertSame([], $engine->commands);
        self::assertStringContainsString('welcome_mail', $screen);
        self::assertStringNotContainsString('profile', $screen);
    }

    public function testDescribe(): void
    {
        $engine = new RecordingSubscriptionEngine([
            new Subscription(
                'profile',
                'projector',
                RunMode::FromBeginning,
                Status::Error,
                2,
                SubscriptionError::fromThrowable(Status::Active, new RuntimeException('projection broken')),
            ),
        ]);

        $screen = $this->runDashboard($engine, [self::ENTER]);

        self::assertStringContainsString('Overview', $screen);
        self::assertStringContainsString('was active', $screen);
        self::assertStringContainsString('RuntimeException', $screen);
        self::assertStringContainsString('projection broken', $screen);
    }

    public function testShowLoadingError(): void
    {
        $engine = $this->createStub(SubscriptionEngine::class);
        $engine->method('subscriptions')->willThrowException(new RuntimeException('connection refused'));

        $runner = new DashboardRunner();
        $screen = $runner->run(
            static fn () => (new SubscriptionDashboard($engine, terminal: $runner->terminal))->run(),
        );

        self::assertStringContainsString('Failed to load subscriptions: connection refused', $screen);
    }

    /** @param list<string> $inputs */
    private function runDashboard(RecordingSubscriptionEngine $engine, array $inputs): string
    {
        $store = new InMemoryStore([
            Message::create(new stdClass()),
            Message::create(new stdClass()),
            Message::create(new stdClass()),
        ]);

        $runner = new DashboardRunner();

        return $runner->run(
            static fn () => (new SubscriptionDashboard($engine, $store, terminal: $runner->terminal))->run(),
            $inputs,
        );
    }
}
