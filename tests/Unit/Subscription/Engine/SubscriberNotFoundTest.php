<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\SubscriberNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriberNotFound::class)]
final class SubscriberNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = SubscriberNotFound::forSubscriptionId('foo');

        self::assertSame(
            'Subscriber with the subscription id "foo" not found.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
