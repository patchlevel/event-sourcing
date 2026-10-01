<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SubscriptionStatusCommand;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(SubscriptionStatusCommand::class)]
final class SubscriptionStatusCommandTest extends TestCase
{
    public function testListAll(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria())
            ->willReturn([
                new Subscription('profile_1', 'projector', status: Status::Active, position: 10),
                new Subscription('welcome_email', 'processor', status: Status::Paused, position: 5),
            ]);

        $command = new SubscriptionStatusCommand($engine);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('profile_1', $content);
        self::assertStringContainsString('active', $content);
        self::assertStringContainsString('welcome_email', $content);
        self::assertStringContainsString('paused', $content);
    }

    public function testListFiltered(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(['profile_1', 'profile_2'], ['projector']))
            ->willReturn([
                new Subscription('profile_1', 'projector', status: Status::Active, position: 10),
            ]);

        $command = new SubscriptionStatusCommand($engine);

        $input = new ArrayInput([
            '--id' => ['profile_1', 'profile_2'],
            '--group' => ['projector'],
        ]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('profile_1', $content);
        self::assertStringNotContainsString('welcome_email', $content);
    }

    public function testShowOne(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(['profile_1']))
            ->willReturn([
                new Subscription('profile_1', 'projector', status: Status::Active, position: 10),
            ]);

        $command = new SubscriptionStatusCommand($engine);

        $input = new ArrayInput(['id' => 'profile_1']);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('profile_1', $content);
        self::assertStringContainsString('projector', $content);
        self::assertStringContainsString('active', $content);
    }

    public function testShowOneNotFound(): void
    {
        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with(new SubscriptionEngineCriteria(['profile_1']))
            ->willReturn([]);

        $command = new SubscriptionStatusCommand($engine);

        $input = new ArrayInput(['id' => 'profile_1']);
        $output = new BufferedOutput();

        $this->expectException(SubscriptionNotFound::class);

        $command->run($input, $output);
    }
}
