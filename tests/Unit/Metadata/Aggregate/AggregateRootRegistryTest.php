<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootClassNotRegistered;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootNameNotRegistered;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;

final class AggregateRootRegistryTest extends TestCase
{
    public function testEmpty(): void
    {
        $registry = new AggregateRootRegistry([]);

        self::assertFalse($registry->hasAggregateClass('Foo'));
        self::assertCount(0, $registry->aggregateClasses());
    }

    public function testMapping(): void
    {
        $registry = new AggregateRootRegistry(['profile' => Profile::class]);

        self::assertTrue($registry->hasAggregateClass(Profile::class));
        self::assertTrue($registry->hasAggregateName('profile'));

        self::assertEquals('profile', $registry->aggregateName(Profile::class));
        self::assertEquals(Profile::class, $registry->aggregateClass('profile'));

        self::assertEquals(['profile' => Profile::class], $registry->aggregateClasses());
        self::assertEquals([Profile::class => 'profile'], $registry->aggregateNames());
    }

    public function testClassNotRegistered(): void
    {
        $this->expectException(AggregateRootClassNotRegistered::class);

        $registry = new AggregateRootRegistry([]);
        $registry->aggregateName(Profile::class);
    }

    public function testNameNotRegistered(): void
    {
        $this->expectException(AggregateRootNameNotRegistered::class);

        $registry = new AggregateRootRegistry([]);
        $registry->aggregateClass('profile');
    }
}
