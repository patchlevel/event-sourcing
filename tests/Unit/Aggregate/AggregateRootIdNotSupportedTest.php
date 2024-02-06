<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Aggregate\AggregateRootIdNotSupported;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Aggregate\AggregateRootIdNotSupported */
final class AggregateRootIdNotSupportedTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateRootIdNotSupported(Profile::class, 1);

        self::assertSame(
            'aggregate root id in class "Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile" must be instance of "Patchlevel\EventSourcing\Aggregate\AggregateRootId", got "int"',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
