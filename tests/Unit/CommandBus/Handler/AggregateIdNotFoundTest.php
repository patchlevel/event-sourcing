<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus\Handler;

use Patchlevel\EventSourcing\CommandBus\Handler\AggregateIdNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(AggregateIdNotFound::class)]
final class AggregateIdNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateIdNotFound(CreateProfile::class);

        self::assertSame(
            sprintf('Missing `Id` Attribute in command %s', CreateProfile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
