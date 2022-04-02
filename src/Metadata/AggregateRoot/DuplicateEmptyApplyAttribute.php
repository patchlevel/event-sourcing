<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class DuplicateEmptyApplyAttribute extends MetadataException
{
    public function __construct(string $methodName)
    {
        parent::__construct(sprintf(
            'The method [%s] has multiple apply attributes given without an event name which is not allowed.',
            $methodName
        ));
    }
}
