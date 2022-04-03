<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Patchlevel\EventSourcing\WatchServer\WatchServer;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\Command\WatchCommand */
final class WatchCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $watchServer = $this->prophesize(WatchServer::class);

        $watchServer->start()->shouldBeCalledOnce();
        $watchServer->host()->willReturn('tcp://foo.bar');
        $watchServer->listen(Argument::any())->shouldBeCalled();

        $serializer = $this->prophesize(Serializer::class);

        $command = new WatchCommand(
            $watchServer->reveal(),
            $serializer->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('Server listening on tcp://foo.bar', $content);
    }
}
