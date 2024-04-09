<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class MultipleDataSubjectId extends MetadataException
{
    public function __construct(string $firstProperty, string $secondProperty)
    {
        parent::__construct(
            sprintf(
                'Multiple data subject id found: %s and %s.',
                $firstProperty,
                $secondProperty,
            ),
        );
    }
}
