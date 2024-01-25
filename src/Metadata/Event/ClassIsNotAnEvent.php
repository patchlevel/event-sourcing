<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class ClassIsNotAnEvent extends MetadataException
{
    /** @param class-string $class */
    public function __construct(string $class)
    {
        parent::__construct(sprintf('class %s is not an event', $class));
    }
}
