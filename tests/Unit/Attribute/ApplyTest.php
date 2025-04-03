<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Apply::class)]
final class ApplyTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Apply(Profile::class);

        self::assertSame(Profile::class, $attribute->eventClass);
    }
}
