<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Lookup\Lookup;

final class LookupResolver implements ArgumentResolver
{
    public function __construct(
        private readonly Store $store,
        private readonly EventRegistry $eventRegistry,
    ) {
    }

    public function resolve(ArgumentMetadata $argument, Message $message): Lookup
    {
        return new Lookup(
            $this->store,
            $this->eventRegistry,
            $message,
        );
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return $argument->type === Lookup::class;
    }
}
