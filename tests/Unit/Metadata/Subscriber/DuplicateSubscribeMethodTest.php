<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateSubscribeMethod;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(DuplicateSubscribeMethod::class)]
final class DuplicateSubscribeMethodTest extends TestCase
{
    public function testDuplicateEvent(): void
    {
        $exception = DuplicateSubscribeMethod::duplicateEvent(Profile::class, ProfileCreated::class, 'methodA', 'methodB');

        self::assertSame(
            sprintf('Two methods "methodA" and "methodB" on the subscriber "%s" are subscribing the event "%s". A subscriber can only listen once to a event, thus this is not allowed.', Profile::class, ProfileCreated::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testMixedWithAll(): void
    {
        $exception = DuplicateSubscribeMethod::mixedWithAll(Profile::class);

        self::assertSame(
            sprintf('The subscriber "%s" is subscribing explicit events and all events. A subscriber can only listen once to a event, thus this is not allowed.', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
