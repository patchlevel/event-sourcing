<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projection;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Projection\Projection;
use ReflectionClass;

use function array_key_exists;

final class AttributeProjectionMetadataFactory implements ProjectionMetadataFactory
{
    /** @var array<class-string<Projection>, ProjectionMetadata> */
    private array $projectionMetadata = [];

    /** @param class-string<Projection> $projection */
    public function metadata(string $projection): ProjectionMetadata
    {
        if (array_key_exists($projection, $this->projectionMetadata)) {
            return $this->projectionMetadata[$projection];
        }

        $reflector = new ReflectionClass($projection);
        $methods = $reflector->getMethods();

        $handleMethods = [];
        $createMethod = null;
        $dropMethod = null;

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Handle::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->eventClass();

                if (array_key_exists($eventClass, $handleMethods)) {
                    throw new DuplicateHandleMethod(
                        $projection,
                        $eventClass,
                        $handleMethods[$eventClass],
                        $method->getName(),
                    );
                }

                $handleMethods[$eventClass] = $method->getName();
            }

            if ($method->getAttributes(Create::class)) {
                if ($createMethod !== null) {
                    throw new DuplicateCreateMethod(
                        $projection,
                        $createMethod,
                        $method->getName(),
                    );
                }

                $createMethod = $method->getName();
            }

            if (!$method->getAttributes(Drop::class)) {
                continue;
            }

            if ($dropMethod !== null) {
                throw new DuplicateDropMethod(
                    $projection,
                    $dropMethod,
                    $method->getName(),
                );
            }

            $dropMethod = $method->getName();
        }

        $metadata = new ProjectionMetadata(
            $handleMethods,
            $createMethod,
            $dropMethod,
        );

        $this->projectionMetadata[$projection] = $metadata;

        return $metadata;
    }
}
