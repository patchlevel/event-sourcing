<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Cleanup;

use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskNotSupported;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DbalCleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(CleanupTaskNotSupported::class)]
final class CleanupTaskNotSupportedTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new CleanupTaskNotSupported(new DropTableTask('projection_table'), DbalCleanupTaskHandler::class);

        self::assertSame(
            sprintf('Task "%s" is not supported by handler "%s"', DropTableTask::class, DbalCleanupTaskHandler::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
