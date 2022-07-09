<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata;

use Patchlevel\EventSourcing\Metadata\ClassFinder;
use PHPUnit\Framework\TestCase;

final class ClassFinderTest extends TestCase
{
    public function testEmpty(): void
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassNames([__DIR__ . '/../../../docs']);

        self::assertCount(0, $classes);
    }

    public function testLoadDirectory(): void
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassNames([__DIR__ . '/../Fixture']);

        self::assertContains('Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile', $classes);
    }
}
