<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;

final class RecordedOnArgumentResolver implements ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, Message $message): DateTimeImmutable
    {
        return $message->header(AggregateHeader::class)->recordedOn;
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return $argument->type === DateTimeImmutable::class;
    }
}
