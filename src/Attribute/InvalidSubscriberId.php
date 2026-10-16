<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use InvalidArgumentException;

use function sprintf;

final class InvalidSubscriberId extends InvalidArgumentException
{
    public function __construct(string $id)
    {
        parent::__construct(
            sprintf('The subscriber id "%s" must not contain "#", it is reserved for the ids of the partitions.', $id),
        );
    }
}
