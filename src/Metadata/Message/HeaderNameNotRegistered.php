<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class HeaderNameNotRegistered extends MetadataException
{
    public function __construct(string $name)
    {
        parent::__construct(
            sprintf(
                'Header name "%s" is not registered',
                $name,
            ),
        );
    }
}
