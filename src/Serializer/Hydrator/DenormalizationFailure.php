<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Hydrator;

use Throwable;

use function sprintf;

final class DenormalizationFailure extends HydratorException
{
    /**
     * @param class-string $class
     * @param class-string $normalizer
     */
    public function __construct(string $class, string $property, string $normalizer, Throwable $e)
    {
        parent::__construct(
            sprintf(
                'denormalization for the property "%s" in the class "%s" with the normalizer "%s" failed.',
                $property,
                $class,
                $normalizer,
            ),
            0,
            $e,
        );
    }
}
