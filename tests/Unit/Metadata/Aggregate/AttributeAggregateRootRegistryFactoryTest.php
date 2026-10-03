<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootAlreadyInRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\NoAggregateRoot;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeAggregateRootRegistryFactory::class)]
final class AttributeAggregateRootRegistryFactoryTest extends TestCase
{
    public function testCreateRegistry(): void
    {
        $factory = new AttributeAggregateRootRegistryFactory();
        $registry = $factory->create([__DIR__ . '/../../Fixture']);

        self::assertTrue($registry->hasAggregateClass(Profile::class));
        self::assertTrue($registry->hasAggregateName('profile'));

        self::assertFalse($registry->hasAggregateClass(Message::class));
    }

    public function testCreateRegistryWithDuplicateEventName(): void
    {
        $this->expectException(AggregateRootAlreadyInRegistry::class);
        $this->expectExceptionMessage('The aggregate name "profile" is already used in the registry. Maybe you defined 2 aggregates with the same name.');

        $factory = new AttributeAggregateRootRegistryFactory();
        $factory->create([__DIR__ . '/Fixture']);
    }

    public function testCreateRegistryWithNonAggregateClass(): void
    {
        $this->expectException(NoAggregateRoot::class);

        $factory = new AttributeAggregateRootRegistryFactory();
        $factory->create([__DIR__ . '/InvalidFixture']);
    }
}
