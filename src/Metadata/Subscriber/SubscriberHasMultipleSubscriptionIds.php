<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function implode;
use function sprintf;

final class SubscriberHasMultipleSubscriptionIds extends MetadataException
{
    /**
     * @param class-string $subscriberClass
     * @param list<string> $ids
     */
    public function __construct(string $subscriberClass, array $ids)
    {
        parent::__construct(
            sprintf(
                'There are multiple subscription ids (%s) defined for the subscriber "%s".',
                $subscriberClass,
                implode(', ', $ids),
            ),
        );
    }
}
