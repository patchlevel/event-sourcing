<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Closure;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscribeMethodMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Throwable;

use function array_key_exists;
use function array_keys;

/** @template-covariant T of object */
final class MetadataSubscriberAccessor
{
    /** @var array<class-string, list<SubscribeMethodMetadata>> */
    private array $subscribeCache = [];

    /** @param T $subscriber */
    public function __construct(
        private readonly object $subscriber,
        private readonly SubscriberMetadata $metadata,
    ) {
    }

    public function metadata(): SubscriberMetadata
    {
        return $this->metadata;
    }

    /** @return T */
    public function subscriber(): object
    {
        return $this->subscriber;
    }

    public function setupMethod(): Closure|null
    {
        $method = $this->metadata->setupMethod;

        if ($method === null) {
            return null;
        }

        return $this->subscriber->$method(...);
    }

    public function teardownMethod(): Closure|null
    {
        $method = $this->metadata->teardownMethod;

        if ($method === null) {
            return null;
        }

        return $this->subscriber->$method(...);
    }

    /** @return Closure():iterable<object>|null */
    public function cleanupMethod(): Closure|null
    {
        $method = $this->metadata->cleanupMethod;

        if ($method === null) {
            return null;
        }

        return $this->subscriber->$method(...);
    }

    /** @return Closure(Message, Throwable):void|null */
    public function failedMethod(): Closure|null
    {
        $method = $this->metadata->failedMethod;

        if ($method === null) {
            return null;
        }

        return $this->subscriber->$method(...);
    }

    /** @return list<class-string|'*'> */
    public function events(): array
    {
        return array_keys($this->metadata->subscribeMethods);
    }

    /**
     * @param class-string $eventClass
     *
     * @return list<SubscribeMethodMetadata>
     */
    public function subscribeMethods(string $eventClass): array
    {
        if (array_key_exists($eventClass, $this->subscribeCache)) {
            return $this->subscribeCache[$eventClass];
        }

        $methods = [];

        if (array_key_exists($eventClass, $this->metadata->subscribeMethods)) {
            $methods[] = $this->metadata->subscribeMethods[$eventClass];
        }

        if (array_key_exists(Subscribe::ALL, $this->metadata->subscribeMethods)) {
            $methods[] = $this->metadata->subscribeMethods[Subscribe::ALL];
        }

        return $this->subscribeCache[$eventClass] = $methods;
    }
}
