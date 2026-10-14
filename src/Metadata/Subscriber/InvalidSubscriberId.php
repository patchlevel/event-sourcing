<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;
use Patchlevel\EventSourcing\Subscription\Subscription;

use function sprintf;

final class InvalidSubscriberId extends MetadataException
{
    /** @param class-string $subscriber */
    public function __construct(string $subscriber, string $id)
    {
        parent::__construct(
            sprintf(
                'The id "%s" of the subscriber "%s" must not contain "%s", it is used as partition separator.',
                $id,
                $subscriber,
                Subscription::PARTITION_SEPARATOR,
            ),
        );
    }
}
