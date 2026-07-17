<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Cleanup;

use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupFailed;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DbalCleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

#[CoversClass(CleanupFailed::class)]
final class CleanupFailedTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new CleanupFailed('foo', $task = new DropTableTask('projection_table'), DbalCleanupTaskHandler::class, $previous = new RuntimeException('error'));

        self::assertSame(
            sprintf('Cleanup of subscription "foo" failed for task "%s" in handler "%s"', DropTableTask::class, DbalCleanupTaskHandler::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertSame($task, $exception->task);
        self::assertSame($previous, $exception->getPrevious());
    }
}
