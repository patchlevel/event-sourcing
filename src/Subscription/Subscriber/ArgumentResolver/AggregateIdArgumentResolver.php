<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Aggregate\StreamNameTranslator;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Store\StreamHeader;

use function class_exists;
use function is_a;

final class AggregateIdArgumentResolver implements ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, Message $message): AggregateRootId
    {
        /** @var class-string<AggregateRootId> $class */
        $class = $argument->type;

        try {
            $id = $message->header(AggregateHeader::class)->aggregateId;

            return $class::fromString($id);
        } catch (HeaderNotFound) {
            // do nothing
        }

        $stream = $message->header(StreamHeader::class)->streamName;
        $aggregateId = StreamNameTranslator::aggregateId($stream);

        return $class::fromString($aggregateId);
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return class_exists($argument->type) && is_a($argument->type, AggregateRootId::class, true);
    }
}
