<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Closure;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscribeMethodMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Throwable;

use function array_key_exists;
use function array_keys;
use function array_map;

/** @template T of object */
final class MetadataSubscriberAccessor
{
    /** @var array<class-string, list<Closure(Message, Subscription):void>> */
    private array $subscribeCache = [];

    /**
     * @param T                      $subscriber
     * @param list<ArgumentResolver> $argumentResolvers
     */
    public function __construct(
        private readonly object $subscriber,
        private readonly SubscriberMetadata $metadata,
        private readonly array $argumentResolvers,
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
     * @return list<Closure(Message, Subscription):void>
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

        $this->subscribeCache[$eventClass] = array_map(
            fn (SubscribeMethodMetadata $method): Closure => $this->createClosure($eventClass, $method),
            $methods,
        );

        return $this->subscribeCache[$eventClass];
    }

    /**
     * @param class-string $eventClass
     *
     * @return Closure(Message, Subscription):void
     */
    private function createClosure(string $eventClass, SubscribeMethodMetadata $method): Closure
    {
        $resolvers = $this->resolvers($eventClass, $method);
        $methodName = $method->name;

        return function (Message $message, Subscription $subscription) use ($methodName, $resolvers): void {
            $arguments = [];

            foreach ($resolvers as $resolver) {
                $arguments[] = $resolver($message, $subscription);
            }

            $this->subscriber->$methodName(...$arguments);
        };
    }

    /**
     * @param class-string $eventClass
     *
     * @return list<Closure(Message, Subscription):mixed>
     */
    private function resolvers(string $eventClass, SubscribeMethodMetadata $method): array
    {
        $resolvers = [];
        $metadata = $this->metadata;

        foreach ($method->arguments as $argument) {
            foreach ($this->argumentResolvers as $resolver) {
                if (!$resolver->support($argument, $eventClass)) {
                    continue;
                }

                $resolvers[] = static function (Message $message, Subscription $subscription) use ($resolver, $argument, $metadata): mixed {
                    return $resolver->resolve($argument, new ArgumentResolverContext($message, $subscription, $metadata));
                };

                continue 2;
            }

            throw new NoSuitableResolver($this->subscriber::class, $method->name, $argument->name);
        }

        return $resolvers;
    }
}
