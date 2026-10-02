<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Metadata\ChainClassLocator;
use Patchlevel\EventSourcing\Metadata\ClassLocator;
use ReflectionClass;

use function array_key_exists;
use function count;

final class AttributeMessageHeaderRegistryFactory implements MessageHeaderRegistryFactory
{
    public function create(ClassLocator $locator): MessageHeaderRegistry
    {
        $classes = (new ChainClassLocator([new InternalHeaderLocator(), $locator]))->locate();

        $result = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Header::class);

            if (count($attributes) === 0) {
                throw new ClassIsNotAHeader($class);
            }

            $headerName = $attributes[0]->newInstance()->name;

            if (array_key_exists($headerName, $result)) {
                throw new HeaderAlreadyInRegistry($headerName, $result[$headerName], $class);
            }

            $result[$headerName] = $class;
        }

        return new MessageHeaderRegistry($result);
    }
}
