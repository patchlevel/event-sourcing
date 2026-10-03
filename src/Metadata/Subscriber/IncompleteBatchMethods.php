<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class IncompleteBatchMethods extends MetadataException
{
    /** @param class-string $subscriber */
    public static function missingFlushMethod(string $subscriber): self
    {
        return new self(
            sprintf(
                'The subscriber "%s" uses batching but does not define a method marked with the #[BatchFlush] attribute.',
                $subscriber,
            ),
        );
    }
}
