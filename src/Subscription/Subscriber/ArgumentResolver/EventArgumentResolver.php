<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;

final class EventArgumentResolver implements ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, Message $message): object
    {
        return $message->event();
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return $argument->type->isIdentifiedBy($eventClass);
    }
}
