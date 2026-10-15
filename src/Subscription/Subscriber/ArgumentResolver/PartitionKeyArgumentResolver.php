<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Attribute\PartitionKey;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;

/** Resolves the partition key of the partition that processes the message, or null without partitions. */
final class PartitionKeyArgumentResolver implements ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, ArgumentResolverContext $context): string|null
    {
        return $context->subscription->partitionKey();
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return $argument->hasAttribute(PartitionKey::class);
    }
}
