<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Attribute\Subscribe */
final class SubscribeTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Subscribe(Profile::class);

        self::assertSame(Profile::class, $attribute->eventClass);
    }
}
