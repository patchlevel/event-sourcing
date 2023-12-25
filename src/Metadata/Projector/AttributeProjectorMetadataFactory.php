<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Projection;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use ReflectionClass;

use function array_key_exists;

final class AttributeProjectorMetadataFactory implements ProjectorMetadataFactory
{
    /** @var array<class-string, ProjectorMetadata> */
    private array $projectorMetadata = [];

    /** @param class-string $projector */
    public function metadata(string $projector): ProjectorMetadata
    {
        if (array_key_exists($projector, $this->projectorMetadata)) {
            return $this->projectorMetadata[$projector];
        }

        $reflector = new ReflectionClass($projector);

        $attributes = $reflector->getAttributes(Projection::class);

        if ($attributes === []) {
            throw new ClassIsNotAProjector($projector);
        }

        $projection = $attributes[0]->newInstance();

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
            $projection->name(),
            $projection->version(),
            $subscribeMethods,
            $createMethod,
            $dropMethod,
        );

        $this->projectorMetadata[$projector] = $metadata;

        return $metadata;
    }
}
