<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\DefaultEventEmitter;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\EventEmitter;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\NoopEventEmitter;

final class EventEmitterResolver implements ArgumentResolver
{
    public function __construct(
        private readonly Store $store,
    ) {
    }

    public function resolve(ArgumentMetadata $argument, ArgumentResolverContext $context): EventEmitter
    {
        $subscriber = $context->subscriber;

        $enabled = match (true) {
            $subscriber->disableEventEmitting => false,
            $subscriber->enableEventEmittingDuringBoot => true,
            // by default events are only emitted during run, not while booting
            default => $context->subscription->isActive(),
        };

        if (!$enabled) {
            return new NoopEventEmitter();
        }

        return new DefaultEventEmitter(
            $this->store,
            'subscription_' . $context->subscription->id(),
        );
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return $argument->type->isIdentifiedBy(EventEmitter::class);
    }
}
