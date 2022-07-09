<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;

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
}
