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
        $classes = $finder->findClassNames([__DIR__ . '/../Fixture']);

        $this->assertEquals([
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\Dummy2Projection',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\DummyProjection',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\Email',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\EmailNormalizer',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\EmptyEvent',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\Message',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\MessageDeleted',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\MessageId',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\MessagePublished',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\NotNormalizedProfileCreated',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\Profile',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\ProfileCreated',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\ProfileId',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\ProfileIdNormalizer',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\ProfileInvalid',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\ProfileVisited',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\ProfileWithSnapshot',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\ProfileWithSuppressAll',
            'Patchlevel\\EventSourcing\\Tests\\Unit\\Fixture\\SimpleEvent',
        ], $classes);
    }
}
