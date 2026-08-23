<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Serializer\EventTagExtractorError;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(EventTagExtractorError::class)]
final class EventTagExtractorErrorTest extends TestCase
{
    public function testInvalidValueType(): void
    {
        $exception = EventTagExtractorError::invalidValueType(ProfileCreated::class, 'profileId', 1.5);

        self::assertSame(
            sprintf(
                'Event tag value for property "profileId" in class "%s" must be stringable, float given',
                ProfileCreated::class,
            ),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
