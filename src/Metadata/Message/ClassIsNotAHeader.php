<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class ClassIsNotAHeader extends MetadataException
{
    /** @param class-string $class */
    public function __construct(string $class)
    {
        parent::__construct(sprintf('class %s is not a header', $class));
    }
}
