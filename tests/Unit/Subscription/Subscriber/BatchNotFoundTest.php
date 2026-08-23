<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber;

use Patchlevel\EventSourcing\Subscription\Subscriber\BatchNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BatchNotFound::class)]
final class BatchNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new BatchNotFound('foo');

        self::assertSame(
            'No batch found for subscription "foo".',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
