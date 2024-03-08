<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class ClassIsNotASubscriber extends MetadataException
{
    /** @param class-string $class */
    public function __construct(string $class)
    {
        parent::__construct(
            sprintf(
                'Class "%s" is not a subscriber',
                $class,
            ),
        );
    }
}
