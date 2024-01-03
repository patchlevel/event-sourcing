<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
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

        $attributes = $reflector->getAttributes(Projector::class);

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

            if ($method->getAttributes(Setup::class)) {
                if ($createMethod) {
                    throw new DuplicateSetupMethod(
                        $projector,
                        $createMethod,
                        $method->getName(),
                    );
                }

                $createMethod = $method->getName();
            }

            if (!$method->getAttributes(Teardown::class)) {
                continue;
            }

            if ($dropMethod) {
                throw new DuplicateTeardownMethod(
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
