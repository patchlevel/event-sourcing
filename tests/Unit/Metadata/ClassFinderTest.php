<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata;

use Patchlevel\EventSourcing\Metadata\ClassFinder;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassFinder::class)]
final class ClassFinderTest extends TestCase
{
    public function testEmpty(): void
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassNames([__DIR__ . '/../../../docs']);

        self::assertCount(0, $classes);
    }

    public function testNoPaths(): void
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassNames([]);

        self::assertCount(0, $classes);
    }

    public function testLoadDirectory(): void
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassNames([__DIR__ . '/../Fixture']);

        self::assertContains(Profile::class, $classes);
    }
}
