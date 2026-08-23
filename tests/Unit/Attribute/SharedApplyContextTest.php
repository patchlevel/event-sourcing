<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\SharedApplyContext;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SharedApplyContext::class)]
final class SharedApplyContextTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new SharedApplyContext([Profile::class]);

        self::assertSame([Profile::class], $attribute->aggregates);
    }
}
