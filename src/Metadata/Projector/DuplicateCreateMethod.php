<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class DuplicateCreateMethod extends MetadataException
{
    /**
     * @param class-string $projector
     */
    public function __construct(string $projector, string $fistMethod, string $secondMethod)
    {
        parent::__construct(
            sprintf(
                'Two methods "%s" and "%s" on the projection "%s" have been marked as "create" methods. Only one method can be defined like this.',
                $fistMethod,
                $secondMethod,
                $projector,
            )
        );
    }
}
