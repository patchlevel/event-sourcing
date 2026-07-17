<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\HandlerNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(HandlerNotFound::class)]
final class HandlerNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new HandlerNotFound(CreateProfile::class);

        self::assertSame(
            sprintf('Handler for command "%s" not found', CreateProfile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
