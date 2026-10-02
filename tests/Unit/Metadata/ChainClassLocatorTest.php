<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata;

use Patchlevel\EventSourcing\Metadata\ChainClassLocator;
use Patchlevel\EventSourcing\Metadata\InMemoryClassLocator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainClassLocator::class)]
final class ChainClassLocatorTest extends TestCase
{
    public function testLocateMergesAndDeduplicates(): void
    {
        $locator = new ChainClassLocator([
            new InMemoryClassLocator([FooHeader::class, BazHeader::class]),
            new InMemoryClassLocator([BazHeader::class, Profile::class]),
        ]);

        self::assertSame([FooHeader::class, BazHeader::class, Profile::class], $locator->locate());
    }

    public function testEmpty(): void
    {
        $locator = new ChainClassLocator([]);

        self::assertSame([], $locator->locate());
    }
}
