<?php

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadata;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;

final class MetadataProjectorAccessor implements ProjectorAccessor
{
    /**
     * @var array<class-string, iterable<Closure>>
     */
    private array $subscribeCache = [];

    public function __construct(
        private readonly object $projector,
        private readonly ProjectorMetadata $metadata,
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

        return $this->projector->$method(...);
    }

    public function teardownMethod(): Closure|null
    {
        $method = $this->metadata->teardownMethod;

        if ($method === null) {
            return null;
        }

        return $this->projector->$method(...);
    }

    /**
     * @param class-string $eventClass
     *
     * @return iterable<Closure>
     */
    public function subscribeMethods(string $eventClass): iterable
    {
        if (array_key_exists($eventClass, $this->subscribeCache)) {
            return $this->subscribeCache[$eventClass];
        }

        $methods = array_merge(
            $this->metadata->subscribeMethods[$eventClass] ?? [],
            $this->metadata->subscribeMethods[Subscribe::ALL] ?? [],
        );

        $this->subscribeCache[$eventClass] = array_map(
            fn(string $method) => $this->projector->$method(...),
            $methods,
        );

        return $this->subscribeCache[$eventClass];
    }
}