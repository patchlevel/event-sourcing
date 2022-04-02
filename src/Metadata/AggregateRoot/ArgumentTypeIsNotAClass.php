<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class ArgumentTypeIsNotAClass extends MetadataException
{
    public function __construct(string $methodName, string $type)
    {
        parent::__construct(sprintf('The type [%s] is not a valid class for method [%s].', $type, $methodName));
    }
}
