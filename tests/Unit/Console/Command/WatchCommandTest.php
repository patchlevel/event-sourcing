<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

#[CoversClass(WatchCommand::class)]
final class WatchCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessfulWithLogger(): void
    {
        $store = new InMemoryStore();

        $serializer = $this->prophesize(EventSerializer::class);

        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $commandTest = new CommandTester(
            new WatchCommand(
                $store,
                $serializer->reveal(),
                $headersSerializer->reveal(),
            ),
        );

        $commandTest->execute(
            // inputs
            ['--run-limit' => 1],
            // options
            [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
                'capture_stderr_separately' => true,
            ],
        );

        $display = $commandTest->getErrorOutput();
        self::assertStringContainsString('Worker terminated', $display);
    }

    public function testMaxIterationReached(): void
    {
        $store = new InMemoryStore();

        $serializer = $this->prophesize(EventSerializer::class);

        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $commandTest = new CommandTester(
            new WatchCommand(
                $store,
                $serializer->reveal(),
                $headersSerializer->reveal(),
            ),
        );

        $runLimit = 2;

        $commandTest->execute(
            // inputs
            [
                '--sleep' => 0,
                '--run-limit' => $runLimit,
            ],
            // options
            [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
                'capture_stderr_separately' => true,
            ],
        );

        $display = $commandTest->getErrorOutput();
        self::assertStringContainsString(
            sprintf('Worker stopped due to maximum iteration of %d', $runLimit),
            $display,
        );
    }
}
