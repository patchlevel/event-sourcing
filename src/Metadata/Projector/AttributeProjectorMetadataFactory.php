<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use ReflectionClass;

use function array_key_exists;

final class AttributeProjectorMetadataFactory implements ProjectorMetadataFactory
{
    /** @var array<class-string, ProjectorMetadata> */
    private array $projectorMetadata = [];

    /**
     * @param class-string $projector
     */
    public function metadata(string $projector): ProjectorMetadata
    {
        if (array_key_exists($projector, $this->projectorMetadata)) {
            return $this->projectorMetadata[$projector];
        }

        $reflector = new ReflectionClass($projector);

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
                        $projector,
                        $eventClass,
                        $handleMethods[$eventClass],
                        $method->getName()
                    );
                }

                $handleMethods[$eventClass] = $method->getName();
            }

            if ($method->getAttributes(Create::class)) {
                if ($createMethod) {
                    throw new DuplicateCreateMethod(
                        $projector,
                        $createMethod,
                        $method->getName()
                    );
                }

                $createMethod = $method->getName();
            }

            if (!$method->getAttributes(Drop::class)) {
                continue;
            }

            if ($dropMethod) {
                throw new DuplicateDropMethod(
                    $projector,
                    $dropMethod,
                    $method->getName()
                );
            }

            $dropMethod = $method->getName();
        }

        $metadata = new ProjectorMetadata(
            $handleMethods,
            $createMethod,
            $dropMethod
        );

        $this->projectorMetadata[$projector] = $metadata;

        return $metadata;
    }
}
