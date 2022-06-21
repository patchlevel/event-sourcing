<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class ArgumentTypeIsMissing extends MetadataException
{
    public function __construct(string $methodName)
    {
        parent::__construct(sprintf('The method [%s] is no type specified.', $methodName));
    }
}
