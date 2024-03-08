<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class DuplicateTeardownMethod extends MetadataException
{
    /** @param class-string $subscriber */
    public function __construct(string $subscriber, string $fistMethod, string $secondMethod)
    {
        parent::__construct(
            sprintf(
                'Two methods "%s" and "%s" on the subscriber "%s" have been marked as "teardown" methods. Only one method can be defined like this.',
                $fistMethod,
                $secondMethod,
                $subscriber,
            ),
        );
    }
}
