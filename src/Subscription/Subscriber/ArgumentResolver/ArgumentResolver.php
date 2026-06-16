<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;

interface ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, ArgumentResolverContext $context): mixed;

    public function support(ArgumentMetadata $argument, string $eventClass): bool;
}
