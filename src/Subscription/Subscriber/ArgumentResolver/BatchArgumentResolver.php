<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchManager;

final class BatchArgumentResolver implements ArgumentResolver
{
    public function __construct(
        private readonly BatchManager $batchManager,
    ) {
    }

    public function resolve(ArgumentMetadata $argument, ArgumentResolverContext $context): mixed
    {
        return $this->batchManager->get($context->subscription->id())->state;
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return $argument->batch;
    }
}
