<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Closure;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Subscription\RunMode;

use function array_key_exists;
use function array_map;
use function array_merge;

final class MetadataSubscriberAccessor implements SubscriberAccessor
{
    /** @var array<class-string, list<Closure(Message):void>> */
    private array $subscribeCache = [];

    public function __construct(
        private readonly object $subscriber,
        private readonly SubscriberMetadata $metadata,
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
            /** @return Closure(Message):void */
            fn (string $method) => $this->subscriber->$method(...),
            $methods,
        );

        return $this->subscribeCache[$eventClass];
    }
}
