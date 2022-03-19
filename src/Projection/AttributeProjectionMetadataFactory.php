<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use function array_key_exists;
use function sprintf;

final class AttributeProjectionMetadataFactory implements ProjectionMetadataFactory
{
    /** @var array<class-string<Projection>, ProjectionMetadata> */
    private array $projectionMetadata = [];

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
                        $metadata->handleMethods[$eventClass]->methodName,
                        $method->getName()
                    );
                }

                $metadata->handleMethods[$eventClass] = new ProjectionHandleMetadata(
                    $method->getName(),
                    $this->passMessage($method)
                );
            }

            if ($method->getAttributes(Create::class)) {
                if ($metadata->createMethod) {
                    throw new MetadataException(sprintf(
                        'There can only be one create method in a projection. Defined in "%s" and "%s".',
                        $metadata->createMethod,
                        $method->getName()
                    ));
                }

                $metadata->createMethod = $method->getName();
            }

            if (!$method->getAttributes(Drop::class)) {
                continue;
            }

            if ($metadata->dropMethod) {
                throw new MetadataException(sprintf(
                    'There can only be one drop method in a projection. Defined in "%s" and "%s".',
                    $metadata->dropMethod,
                    $method->getName()
                ));
            }

            $metadata->dropMethod = $method->getName();
        }

        return $metadata;
    }

    private function passMessage(ReflectionMethod $method): bool
    {
        $parameters = $method->getParameters();

        if ($parameters === []) {
            return false;
        }

        $firstParameter = $parameters[0];
        $type = $firstParameter->getType();

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        return $type->getName() === Message::class;
    }
}
