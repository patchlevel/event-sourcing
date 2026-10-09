<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber;

use Patchlevel\EventSourcing\Subscription\Subscriber\DuplicateSubscriberId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DuplicateSubscriberId::class)]
final class DuplicateSubscriberIdTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new DuplicateSubscriberId('foo');

        self::assertSame(
            'Duplicate subscriber id "foo".',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
