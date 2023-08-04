<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\OutboxConsumeCommand;
use Patchlevel\EventSourcing\Outbox\OutboxConsumer;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\Command\OutboxConsumeCommand */
final class OutboxConsumeCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $consumer = $this->prophesize(OutboxConsumer::class);
        $consumer->consume(100)->shouldBeCalled();

        $command = new OutboxConsumeCommand(
            $consumer->reveal(),
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
    }

    public function testSuccessfulWithAllLimits(): void
    {
        $consumer = $this->prophesize(OutboxConsumer::class);
        $consumer->consume(200)->shouldBeCalled();

        $command = new OutboxConsumeCommand(
            $consumer->reveal(),
        );

        $input = new ArrayInput([
            '--message-limit' => 200,
            '--run-limit' => 1,
            '--memory-limit' => '10GB',
            '--time-limit' => 3600,
            '--sleep' => 1000,
        ]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
    }
}
