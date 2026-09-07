<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\InvalidHandleMethod;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(InvalidHandleMethod::class)]
final class InvalidHandleMethodTest extends TestCase
{
    public function testNoParameters(): void
    {
        $exception = InvalidHandleMethod::noParameters(Profile::class, 'handle');

        self::assertSame(
            sprintf('Method "handle" in aggregate "%s" has no parameters', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testIncompatibleType(): void
    {
        $exception = InvalidHandleMethod::incompatibleType(Profile::class, 'handle');

        self::assertSame(
            sprintf('Method "handle" in aggregate "%s" has no compatible type', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
