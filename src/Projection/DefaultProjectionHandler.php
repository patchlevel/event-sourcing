<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use ReflectionClass;

use function array_key_exists;
use function in_array;

final class DefaultProjectionHandler implements ProjectionHandler
{
    /** @var array<class-string<Projection>, ProjectionMetadata> */
    private array $projectionMetadata = [];

    /** @var iterable<Projection> */
    private iterable $projections;

    /**
     * @param iterable<Projection> $projections
     */
    public function __construct(iterable $projections)
    {
        $this->projections = $projections;
    }

    public function handle(AggregateChanged $event, ?array $onlyProjections = null): void
    {
        foreach ($this->projections as $projection) {
            if ($onlyProjections && !in_array($projection::class, $onlyProjections)) {
                continue;
            }

            $metadata = $this->metadata($projection);

            if (!array_key_exists($event::class, $metadata->handleMethods)) {
                continue;
            }

            $method = $metadata->handleMethods[$event::class];

            $projection->$method($event);
        }
    }

    public function create(?array $onlyProjections = null): void
    {
        foreach ($this->projections as $projection) {
            if ($onlyProjections && !in_array($projection::class, $onlyProjections)) {
                continue;
            }

            $metadata = $this->metadata($projection);
            $method = $metadata->createMethod;

            if (!$method) {
                continue;
            }

            $projection->$method();
        }
    }

    public function drop(?array $onlyProjections = null): void
    {
        foreach ($this->projections as $projection) {
            if ($onlyProjections && !in_array($projection::class, $onlyProjections)) {
                continue;
            }

            $metadata = $this->metadata($projection);
            $method = $metadata->dropMethod;

            if (!$method) {
                continue;
            }

            $projection->$method();
        }
    }

    public function metadata(Projection $projection): ProjectionMetadata
    {
        if (array_key_exists($projection::class, $this->projectionMetadata)) {
            return $this->projectionMetadata[$projection::class];
        }

        $reflector = new ReflectionClass($projection::class);
        $methods = $reflector->getMethods();

        $metadata = new ProjectionMetadata();

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Handle::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->aggregateChangedClass();

                if (array_key_exists($eventClass, $metadata->handleMethods)) {
                    throw new DuplicateHandleMethod(
                        $projection::class,
                        $eventClass,
                        $metadata->handleMethods[$eventClass],
                        $method->getName()
                    );
                }

                $metadata->handleMethods[$eventClass] = $method->getName();
            }

            if ($method->getAttributes(Create::class)) {
                $metadata->createMethod = $method->getName();
            }

            if (!$method->getAttributes(Drop::class)) {
                continue;
            }

            $metadata->dropMethod = $method->getName();
        }

        return $metadata;
    }
}
