<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function get_debug_type;
use function sprintf;

final class SubscriptionIdWrongType extends MetadataException
{
    /** @param class-string $subscriberClass */
    public function __construct(string $subscriberClass, mixed $subscriptionId)
    {
        parent::__construct(
            sprintf(
                'The subscription id defined for the subscriber "%s" has a wrong type. The Id needs to be a string, "%s" given.',
                $subscriberClass,
                get_debug_type($subscriptionId)
            ),
        );
    }
}
