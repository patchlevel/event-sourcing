<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class SubscribeAllNotSupported extends MetadataException
{
    /** @param class-string $projector */
    public function __construct(string $projector, string $method)
    {
        parent::__construct(
            sprintf(
                'subscribe all (*) not supported in projector "%s" method "%s"',
                $projector,
                $method,
            ),
        );
    }
}
