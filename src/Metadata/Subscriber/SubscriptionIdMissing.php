<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class SubscriptionIdMissing extends MetadataException
{
    /** @param class-string $subscriberClass */
    public function __construct(string $subscriberClass)
    {
        parent::__construct(
            sprintf(
                'There is no subscription id defined for the subscriber "%s". Define it via #[Subscriber] or #[SubscriptionId] attribute.',
                $subscriberClass,
            ),
        );
    }
}
