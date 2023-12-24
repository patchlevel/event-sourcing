<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use ReflectionClass;

use function array_key_exists;

final class AttributeProjectorMetadataFactory implements ProjectorMetadataFactory
{
    /** @var array<class-string<Projector>, ProjectorMetadata> */
    private array $projectorMetadata = [];

    /** @param class-string<Projector> $projector */
    public function metadata(string $projector): ProjectorMetadata
    {
        if (array_key_exists($projector, $this->projectorMetadata)) {
            return $this->projectorMetadata[$projector];
        }

        $reflector = new ReflectionClass($projector);

        $methods = $reflector->getMethods();

        $subscribeMethods = [];
        $createMethod = null;
        $dropMethod = null;

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Subscribe::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->eventClass();

                if (array_key_exists($eventClass, $subscribeMethods)) {
                    throw new DuplicateSubscribeMethod(
                        $projector,
                        $eventClass,
                        $subscribeMethods[$eventClass],
                        $method->getName(),
                    );
                }

                $subscribeMethods[$eventClass] = $method->getName();
            }

            if ($method->getAttributes(Create::class)) {
                if ($createMethod) {
                    throw new DuplicateCreateMethod(
                        $projector,
                        $createMethod,
                        $method->getName(),
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
                    $method->getName(),
                );
            }

            $dropMethod = $method->getName();
        }

        $metadata = new ProjectorMetadata(
            $subscribeMethods,
            $createMethod,
            $dropMethod,
        );

        $this->projectorMetadata[$projector] = $metadata;

        return $metadata;
    }
}
