<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use ReflectionClass;

use function array_key_exists;

final class AttributeSubscriberMetadataFactory implements SubscriberMetadataFactory
{
    /** @var array<class-string, SubscriberMetadata> */
    private array $subscriberMetadata = [];

    /** @param class-string $subscriber */
    public function metadata(string $subscriber): SubscriberMetadata
    {
        if (array_key_exists($subscriber, $this->subscriberMetadata)) {
            return $this->subscriberMetadata[$subscriber];
        }

        $reflector = new ReflectionClass($subscriber);

        $attributes = $reflector->getAttributes(Subscriber::class);

        if ($attributes === []) {
            throw new ClassIsNotASubscriber($subscriber);
        }

        $subscriberInfo = $attributes[0]->newInstance();

        $methods = $reflector->getMethods();

        $subscribeMethods = [];
        $createMethod = null;
        $dropMethod = null;

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Subscribe::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->eventClass;

                $subscribeMethods[$eventClass][] = $method->getName();
            }

            if ($method->getAttributes(Setup::class)) {
                if ($createMethod !== null) {
                    throw new DuplicateSetupMethod(
                        $subscriber,
                        $createMethod,
                        $method->getName(),
                    );
                }

                $createMethod = $method->getName();
            }

            if (!$method->getAttributes(Teardown::class)) {
                continue;
            }

            if ($dropMethod !== null) {
                throw new DuplicateTeardownMethod(
                    $subscriber,
                    $dropMethod,
                    $method->getName(),
                );
            }

            $dropMethod = $method->getName();
        }

        $metadata = new SubscriberMetadata(
            $subscriberInfo->id,
            $subscriberInfo->group,
            $subscriberInfo->runMode,
            $subscribeMethods,
            $createMethod,
            $dropMethod,
        );

        $this->subscriberMetadata[$subscriber] = $metadata;

        return $metadata;
    }
}
