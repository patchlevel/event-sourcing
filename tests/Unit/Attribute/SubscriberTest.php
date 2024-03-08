<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Attribute\Subscriber */
final class SubscriberTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Subscriber('foo');

        self::assertSame('foo', $attribute->id);
    }
}
