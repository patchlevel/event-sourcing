<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Hydrator;

use TypeError;

use function sprintf;

final class TypeMismatch extends HydratorException
{
    /** @param class-string $class */
    public function __construct(string $class, string $property, TypeError|null $previous = null)
    {
        parent::__construct(
            sprintf(
                'The value could not be set because the expected type of the property "%s" in class "%s" does not match.',
                $property,
                $class,
            ),
            0,
            $previous,
        );
    }
}
