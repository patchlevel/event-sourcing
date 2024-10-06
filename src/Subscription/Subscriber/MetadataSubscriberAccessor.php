<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Closure;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscribeMethodMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;

final class MetadataSubscriberAccessor implements SubscriberAccessor, RealSubscriberAccessor
{
    /** @var array<class-string, list<Closure(Message):void>> */
    private array $subscribeCache = [];

    /** @param list<ArgumentResolver> $argumentResolvers */
    public function __construct(
        private readonly object $subscriber,
        private readonly SubscriberMetadata $metadata,
        private readonly array $argumentResolvers,
    ) {
    }

    public function id(): string
    {
        return $this->metadata->id;
    }

    public function group(): string
    {
        return $this->metadata->group;
    }

    public function runMode(): RunMode
    {
        return $this->metadata->runMode;
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

    /** @return list<class-string|'*'> */
    public function events(): array
    {
        return array_keys($this->metadata->subscribeMethods);
    }

    /**
     * @param class-string $eventClass
     *
     * @return list<Closure(Message):void>
     */
    public function subscribeMethods(string $eventClass): array
    {
        if (array_key_exists($eventClass, $this->subscribeCache)) {
            return $this->subscribeCache[$eventClass];
        }

        $methods = array_merge(
            $this->metadata->subscribeMethods[$eventClass] ?? [],
            $this->metadata->subscribeMethods[Subscribe::ALL] ?? [],
        );

        $this->subscribeCache[$eventClass] = array_map(
            fn (SubscribeMethodMetadata $method): Closure => $this->createClosure($eventClass, $method),
            $methods,
        );

        return $this->subscribeCache[$eventClass];
    }

    /**
     * @param class-string $eventClass
     *
     * @return Closure(Message):void
     */
    private function createClosure(string $eventClass, SubscribeMethodMetadata $method): Closure
    {
        $resolvers = $this->resolvers($eventClass, $method);
        $methodName = $method->name;

        return function (Message $message) use ($methodName, $resolvers): void {
            $arguments = [];

            foreach ($resolvers as $resolver) {
                $arguments[] = $resolver($message);
            }

            $this->subscriber->$methodName(...$arguments);
        };
    }

    /**
     * @param class-string $eventClass
     *
     * @return list<Closure(Message):mixed>
     */
    private function resolvers(string $eventClass, SubscribeMethodMetadata $method): array
    {
        $resolvers = [];

        foreach ($method->arguments as $argument) {
            foreach ($this->argumentResolvers as $resolver) {
                if (!$resolver->support($argument, $eventClass)) {
                    continue;
                }

                $resolvers[] = static function (Message $message) use ($resolver, $argument): mixed {
                    return $resolver->resolve($argument, $message);
                };

                continue 2;
            }

            throw new NoSuitableResolver($this->subscriber::class, $method->name, $argument->name);
        }

        return $resolvers;
    }

    public function realSubscriber(): object
    {
        return $this->subscriber;
    }
}
