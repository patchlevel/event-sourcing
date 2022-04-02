<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class MixedApplyAttributeUsage extends MetadataException
{
    public function __construct(string $methodName)
    {
        parent::__construct(sprintf(
            'The method [%s] has at least one apply attribute with an event name and one without which is not allowed.',
            $methodName
        ));
    }
}
