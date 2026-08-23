<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Cleanup;

use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use Patchlevel\EventSourcing\Subscription\Cleanup\NoHandlerForCleanupTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(NoHandlerForCleanupTask::class)]
final class NoHandlerForCleanupTaskTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new NoHandlerForCleanupTask($task = new DropTableTask('projection_table'));

        self::assertSame(
            sprintf('No cleanup handler for task %s', DropTableTask::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertSame($task, $exception->task);
    }
}
