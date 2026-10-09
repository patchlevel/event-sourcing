<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionDashboardCommand;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Console\Tui\DashboardRunner;
use Patchlevel\EventSourcing\Tests\Unit\Console\Tui\RecordingSubscriptionEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Tui\Tui;

use function class_exists;

#[CoversClass(SubscriptionDashboardCommand::class)]
final class SubscriptionDashboardCommandTest extends TestCase
{
    protected function setUp(): void
    {
        if (class_exists(Tui::class)) {
            return;
        }

        self::markTestSkipped('symfony/tui is not installed');
    }

    public function testRequiresInteractiveTerminal(): void
    {
        $commandTester = new CommandTester(new SubscriptionDashboardCommand(new RecordingSubscriptionEngine()));
        $commandTester->execute([], ['interactive' => false]);

        self::assertSame(2, $commandTester->getStatusCode());
        self::assertStringContainsString('requires an interactive terminal', $commandTester->getDisplay());
    }

    public function testRunWithFilter(): void
    {
        $engine = new RecordingSubscriptionEngine([new Subscription('profile')]);
        $runner = new DashboardRunner();

        $commandTester = new CommandTester(new SubscriptionDashboardCommand($engine, terminal: $runner->terminal));
        $screen = $runner->run(static fn () => $commandTester->execute(['--group' => ['projector']]));

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertEquals(new SubscriptionEngineCriteria(null, ['projector']), $engine->criteria[0]);
        self::assertStringContainsString('group=projector', $screen);
    }
}
