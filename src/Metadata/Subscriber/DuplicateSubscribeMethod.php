<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class DuplicateSubscribeMethod extends MetadataException
{
    public static function duplicateEvent(
        string $subscriber,
        string $event,
        string $fistMethod,
        string $secondMethod,
    ): self {
        return new self(
            sprintf(
                'Two methods "%s" and "%s" on the subscriber "%s" are subscribing the event "%s". A subscriber can only listen once to a event, thus this is not allowed.',
                $fistMethod,
                $secondMethod,
                $subscriber,
                $event,
            ),
        );
    }

    public static function mixedWithAll(string $subscriber): self
    {
        return new self(sprintf(
            'The subscriber "%s" is subscribing explicit events and all events. A subscriber can only listen once to a event, thus this is not allowed.',
            $subscriber,
        ));
    }
}
