<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\CleanerNotConfigured;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CleanerNotConfigured::class)]
final class CleanerNotConfiguredTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new CleanerNotConfigured();

        self::assertSame(
            'Cleaner not configured.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
