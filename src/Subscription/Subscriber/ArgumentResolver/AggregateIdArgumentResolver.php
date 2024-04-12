<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;

use function class_exists;
use function is_a;

final class AggregateIdArgumentResolver implements ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, Message $message): AggregateRootId
    {
        /** @var class-string<AggregateRootId> $class */
        $class = $argument->type;

        $id = $message->header(AggregateHeader::class)->aggregateId;

        return $class::fromString($id);
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return class_exists($argument->type) && is_a($argument->type, AggregateRootId::class, true);
    }
}
