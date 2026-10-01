<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class HeaderAlreadyInRegistry extends MetadataException
{
    public function __construct(string $headerName)
    {
        parent::__construct(sprintf(
            'The header name "%s" is already used in the registry. Maybe you defined an alias which is already used as header name or alias.',
            $headerName,
        ));
    }
}
