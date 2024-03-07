<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class HeaderClassNotRegistered extends MetadataException
{
    public function __construct(string $headerClass)
    {
        parent::__construct(
            sprintf(
                'Header class "%s" is not registered',
                $headerClass,
            ),
        );
    }
}
