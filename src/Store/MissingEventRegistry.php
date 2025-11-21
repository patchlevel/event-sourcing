<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use RuntimeException;

use function sprintf;

final class MissingEventRegistry extends RuntimeException
{
    /** @param class-string $criterionClass */
    public function __construct(string $criterionClass)
    {
        parent::__construct(sprintf('criterion %s not supported without an %s given', $criterionClass, EventRegistry::class));
    }
}
