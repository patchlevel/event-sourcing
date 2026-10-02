<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata;

use Patchlevel\EventSourcing\Metadata\InMemoryClassLocator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryClassLocator::class)]
final class InMemoryClassLocatorTest extends TestCase
{
    public function testLocate(): void
    {
        $locator = new InMemoryClassLocator([FooHeader::class, Profile::class]);

        self::assertSame([FooHeader::class, Profile::class], $locator->locate());
    }
}
