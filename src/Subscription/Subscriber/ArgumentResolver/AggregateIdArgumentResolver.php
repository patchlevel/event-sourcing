<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;

use function in_array;

final class AggregateIdArgumentResolver implements ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, Message $message): string
    {
        return $message->header(AggregateHeader::class)->aggregateId;
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return $argument->type === 'string' && in_array($argument->name, ['aggregateId', 'aggregateRootId']);
    }
}
