<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class MixedTeardownAndCleanupMethods extends MetadataException
{
    public function __construct(
        string $subscriber,
        string $teardownMethod,
        string $cleanupMethod,
    ) {
        parent::__construct(
            sprintf(
                'The subscriber "%s" has a "teardown" method "%s" and a "cleanup" method "%s". Only one of them can be defined.',
                $subscriber,
                $teardownMethod,
                $cleanupMethod,
            ),
        );
    }
}
