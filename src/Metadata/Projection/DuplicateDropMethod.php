<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projection;

use Patchlevel\EventSourcing\Metadata\MetadataException;
use Patchlevel\EventSourcing\Projection\Projection;

use function sprintf;

final class DuplicateDropMethod extends MetadataException
{
    /**
     * @param class-string<Projection> $projection
     */
    public function __construct(string $projection, string $fistMethod, string $secondMethod)
    {
        parent::__construct(
            sprintf(
                'Two methods "%s" and "%s" on the projection "%s" have been marked as "create" methods. Only one method can be defined like this.',
                $fistMethod,
                $secondMethod,
                $projection,
            )
        );
    }
}
