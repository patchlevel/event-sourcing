<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Serializer\EventPayloadNotAnArray;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(EventPayloadNotAnArray::class)]
final class EventPayloadNotAnArrayTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new EventPayloadNotAnArray(ProfileCreated::class, 'foo');

        self::assertSame(
            sprintf('The event "%s" has to be extracted to an array, "string" given.', ProfileCreated::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
