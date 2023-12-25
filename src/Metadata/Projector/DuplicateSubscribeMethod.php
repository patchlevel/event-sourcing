<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class DuplicateSubscribeMethod extends MetadataException
{
    /**
     * @param class-string $projector
     * @param class-string $event
     */
    public function __construct(string $projector, string $event, string $fistMethod, string $secondMethod)
    {
        parent::__construct(
            sprintf(
                'Two methods "%s" and "%s" on the projection "%s" want to subscribe the same event "%s". Only one method can subscribe an event.',
                $fistMethod,
                $secondMethod,
                $projector,
                $event,
            ),
        );
    }
}
