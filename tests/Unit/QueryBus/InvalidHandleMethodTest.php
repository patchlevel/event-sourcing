<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\QueryBus;

use Patchlevel\EventSourcing\QueryBus\InvalidHandleMethod;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(InvalidHandleMethod::class)]
final class InvalidHandleMethodTest extends TestCase
{
    public function testNoParameters(): void
    {
        $exception = InvalidHandleMethod::noParameters(QueryProfile::class, 'handle');

        self::assertSame(
            sprintf('Query handling method "handle" in class "%s" has no parameters', QueryProfile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testIncompatibleType(): void
    {
        $exception = InvalidHandleMethod::incompatibleType(QueryProfile::class, 'handle');

        self::assertSame(
            sprintf('Query handling method "handle" in class "%s" has no compatible type', QueryProfile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
