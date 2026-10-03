<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus\Handler;

use Patchlevel\EventSourcing\CommandBus\Handler\ServiceNotResolvable;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

#[CoversClass(ServiceNotResolvable::class)]
final class ServiceNotResolvableTest extends TestCase
{
    public function testMissingType(): void
    {
        $exception = ServiceNotResolvable::missingType(Profile::class, 'foo');

        self::assertSame(
            sprintf('Missing type for property "foo" in class "%s"', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testTypeNotObject(): void
    {
        $exception = ServiceNotResolvable::typeNotObject(Profile::class, 'foo');

        self::assertSame(
            sprintf('Type for property "foo" in class "%s" must be object', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testMissingContainer(): void
    {
        $exception = ServiceNotResolvable::missingContainer();

        self::assertSame('Container is not configured', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }

    public function testMissingService(): void
    {
        $previous = new RuntimeException('service error');

        $exception = ServiceNotResolvable::missingService(Profile::class, 'handle', 'foo', $previous);

        self::assertSame(
            sprintf('Missing service for parameter "foo" in "%s::handle" . Exception: service error', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
