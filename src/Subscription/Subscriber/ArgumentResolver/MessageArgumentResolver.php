<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;

final class MessageArgumentResolver implements ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, Message $message): Message
    {
        return $message;
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return $argument->type === Message::class;
    }
}
