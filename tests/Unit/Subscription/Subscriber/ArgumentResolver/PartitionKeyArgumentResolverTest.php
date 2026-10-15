<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Attribute\PartitionKey;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\PartitionKeyArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(PartitionKeyArgumentResolver::class)]
final class PartitionKeyArgumentResolverTest extends TestCase
{
    public function testSupport(): void
    {
        $resolver = new PartitionKeyArgumentResolver();

        self::assertTrue($resolver->support(new ArgumentMetadata('tenant', Type::string(), [new PartitionKey()]), 'qux'));
        self::assertFalse($resolver->support(new ArgumentMetadata('tenant', Type::string()), 'qux'));
    }

    public function testResolvePartitionKey(): void
    {
        $resolver = new PartitionKeyArgumentResolver();
        $partition = Subscription::partition(new Subscription('profile'), 'tenant-a', 0);

        self::assertSame(
            'tenant-a',
            $resolver->resolve(
                new ArgumentMetadata('tenant', Type::string(), [new PartitionKey()]),
                new ArgumentResolverContext(new Message(new stdClass()), $partition, new SubscriberMetadata('profile')),
            ),
        );
    }

    public function testResolveWithoutPartition(): void
    {
        $resolver = new PartitionKeyArgumentResolver();

        self::assertNull(
            $resolver->resolve(
                new ArgumentMetadata('tenant', Type::nullable(Type::string()), [new PartitionKey()]),
                new ArgumentResolverContext(new Message(new stdClass()), new Subscription('profile'), new SubscriberMetadata('profile')),
            ),
        );
    }
}
