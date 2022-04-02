<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata;

use Patchlevel\EventSourcing\Metadata\ClassFinder;
use PHPUnit\Framework\TestCase;

class ClassFinderTest extends TestCase
{
    public function testEmpty(): void
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassNames([__DIR__ . '/../../../docs']);

        $this->assertCount(0, $classes);
    }

    public function testLoadDirectory(): void
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassNames([__DIR__ . '/Projection']);

        $this->assertEquals(['Patchlevel\\EventSourcing\\Tests\\Unit\\Metadata\\Projection\\AttributeProjectionMetadataFactoryTest'], $classes);
    }
}
