<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Store\SubscriptionAlreadyExists;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SubscriptionAlreadyExists::class)]
final class SubscriptionAlreadyExistsTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new SubscriptionAlreadyExists('foo-1');

        self::assertSame(
            'Subscription "foo-1" already exists.',
            $exception->getMessage(),
        );

        self::assertSame(0, $exception->getCode());
    }

    public function testCreateWithPrevious(): void
    {
        $previous = new RuntimeException('previous');
        $exception = new SubscriptionAlreadyExists('foo-1', $previous);

        self::assertSame(
            'Subscription "foo-1" already exists.',
            $exception->getMessage(),
        );

        self::assertSame($previous, $exception->getPrevious());
    }
}
