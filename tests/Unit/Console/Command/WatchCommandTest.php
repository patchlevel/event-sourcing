<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function file_exists;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function time;
use function touch;
use function unlink;

#[CoversClass(WatchCommand::class)]
final class WatchCommandTest extends TestCase
{
    public function testSuccessfulWithLogger(): void
    {
        $store = new InMemoryStore();

        $serializer = $this->createMock(EventSerializer::class);

        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $commandTest = new CommandTester(
            new WatchCommand(
                $store,
                $serializer,
                $headersSerializer,
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

        $serializer = $this->createMock(EventSerializer::class);

        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $commandTest = new CommandTester(
            new WatchCommand(
                $store,
                $serializer,
                $headersSerializer,
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

    public function testStopsOnRestartSignal(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'restart');
        touch($file, time() + 60);

        $commandTest = new CommandTester(
            new WatchCommand(
                new InMemoryStore(),
                $this->createMock(EventSerializer::class),
                $this->createMock(HeadersSerializer::class),
            ),
        );

        $commandTest->execute(
            ['--sleep' => 0, '--restart-signal-file' => $file],
            [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
                'capture_stderr_separately' => true,
            ],
        );

        unlink($file);

        self::assertStringContainsString(
            sprintf('Worker stopped due to restart signal from %s', $file),
            $commandTest->getErrorOutput(),
        );
    }

    public function testWithHeartbeat(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'heartbeat');

        $commandTest = new CommandTester(
            new WatchCommand(
                new InMemoryStore(),
                $this->createMock(EventSerializer::class),
                $this->createMock(HeadersSerializer::class),
            ),
        );

        $commandTest->execute(['--run-limit' => 1, '--heartbeat-file' => $file]);

        self::assertFalse(file_exists($file));
    }
}
