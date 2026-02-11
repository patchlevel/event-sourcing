<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Cleanup;

use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use Patchlevel\EventSourcing\Subscription\Cleanup\DefaultCleaner;
use Patchlevel\EventSourcing\Subscription\Cleanup\NoHandlerForCleanupTask;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DefaultCleaner::class)]
final class DefaultCleanerTest extends TestCase
{
    public function testClean(): void
    {
        $handler = new class implements CleanupHandler {
            public bool $called = false;

            public function __invoke(object $task): void
            {
                $this->called = true;
            }

            public function supports(object $task): bool
            {
                return true;
            }
        };

        $cleaner = new DefaultCleaner([$handler]);
        $cleaner->cleanup(
            new Subscription('test', cleanupTasks: [new DropTableTask('test')]),
        );

        self::assertTrue($handler->called);
    }

    public function testCleanupFailed(): void
    {
        $handler = new class implements CleanupHandler {
            public function __invoke(object $task): void
            {
                throw new RuntimeException('Failed to cleanup');
            }

            public function supports(object $task): bool
            {
                return true;
            }
        };

        $cleaner = new DefaultCleaner([$handler]);
        $this->expectException(RuntimeException::class);

        $cleaner->cleanup(
            new Subscription('test', cleanupTasks: [new DropTableTask('test')]),
        );
    }

    public function testNoTasks(): void
    {
        $handler = new class implements CleanupHandler {
            public bool $called = false;

            public function __invoke(object $task): void
            {
                $this->called = true;
            }

            public function supports(object $task): bool
            {
                return true;
            }
        };

        $cleaner = new DefaultCleaner([$handler]);
        $cleaner->cleanup(new Subscription('test'));

        self::assertTrue($handler->called === false);
    }

    public function testNoHandler(): void
    {
        $this->expectException(NoHandlerForCleanupTask::class);

        $cleaner = new DefaultCleaner();
        $cleaner->cleanup(
            new Subscription('test', cleanupTasks: [new DropTableTask('test')]),
        );
    }
}
