<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use ReflectionClass;

use function array_key_exists;

final class AttributeAggregateRootMetadataFactory implements AggregateRootMetadataFactory
{
    /** @var array<class-string<AggregateRoot>, AggregateRootMetadata> */
    private array $aggregateMetadata = [];

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function metadata(string $aggregate): AggregateRootMetadata
    {
        if (array_key_exists($aggregate, $this->aggregateMetadata)) {
            return $this->aggregateMetadata[$aggregate];
        }

        $metadata = new AggregateRootMetadata();

        $reflector = new ReflectionClass($aggregate);
        $attributes = $reflector->getAttributes(SuppressMissingApply::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance->suppressAll()) {
                $metadata->suppressAll = true;

                continue;
            }

            foreach ($instance->suppressEvents() as $event) {
                $metadata->suppressEvents[$event] = true;
            }
        }

        $methods = $reflector->getMethods();

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Apply::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->eventClass();

                if (array_key_exists($eventClass, $metadata->applyMethods)) {
                    throw new DuplicateApplyMethod(
                        $aggregate,
                        $eventClass,
                        $metadata->applyMethods[$eventClass],
                        $method->getName()
                    );
                }

                $metadata->applyMethods[$eventClass] = $method->getName();
            }
        }

        $this->aggregateMetadata[$aggregate] = $metadata;

        return $metadata;
    }
}
