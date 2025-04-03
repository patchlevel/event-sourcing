<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\RunMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subscriber::class)]
final class SubscriberTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Subscriber('foo', RunMode::FromBeginning);

        self::assertSame('foo', $attribute->id);
    }
}
