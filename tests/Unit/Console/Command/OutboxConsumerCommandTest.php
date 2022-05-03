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
final class OutboxConsumerCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $consumer = $this->prophesize(OutboxConsumer::class);
        $consumer->consume(null)->shouldBeCalled();

        $command = new OutboxConsumeCommand(
            $consumer->reveal(),
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
    }

    public function testSuccessfulWithLimit(): void
    {
        $consumer = $this->prophesize(OutboxConsumer::class);
        $consumer->consume(100)->shouldBeCalled();

        $command = new OutboxConsumeCommand(
            $consumer->reveal(),
        );

        $input = new ArrayInput(['--limit' => 100]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
    }
}
