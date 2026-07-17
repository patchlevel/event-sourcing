<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\MultipleHandlersFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(MultipleHandlersFound::class)]
final class MultipleHandlersFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MultipleHandlersFound(CreateProfile::class);

        self::assertSame(
            sprintf('Multiple handlers found for command "%s"', CreateProfile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
