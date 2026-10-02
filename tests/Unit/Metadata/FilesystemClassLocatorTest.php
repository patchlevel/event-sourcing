<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata;

use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Metadata\FilesystemClassLocator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilesystemClassLocator::class)]
final class FilesystemClassLocatorTest extends TestCase
{
    public function testLocateAllClasses(): void
    {
        $locator = new FilesystemClassLocator([__DIR__ . '/../Fixture']);
        $classes = $locator->locate();

        self::assertContains(Profile::class, $classes);
        self::assertContains(FooHeader::class, $classes);
    }

    public function testLocateClassesWithAttribute(): void
    {
        $locator = new FilesystemClassLocator([__DIR__ . '/../Fixture'], Header::class);

        self::assertSame([BazHeader::class, FooHeader::class], $locator->locate());
    }

    public function testNoPaths(): void
    {
        $locator = new FilesystemClassLocator([], Header::class);

        self::assertSame([], $locator->locate());
    }
}
