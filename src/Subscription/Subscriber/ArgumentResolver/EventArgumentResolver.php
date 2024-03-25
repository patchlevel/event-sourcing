<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;

use function class_exists;
use function is_a;

final class EventArgumentResolver implements ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, Message $message): object
    {
        return $message->event();
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return class_exists($argument->type) && is_a($eventClass, $argument->type, true);
    }
}
