<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootNameNotRegistered;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateRootNameNotRegistered::class)]
final class AggregateRootNameNotRegisteredTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateRootNameNotRegistered('profile');

        self::assertSame(
            'Aggregate root name "profile" is not registered',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
