<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class HeaderAlreadyInRegistry extends MetadataException
{
    /**
     * @param class-string $registeredClass
     * @param class-string $class
     */
    public function __construct(string $headerName, string $registeredClass, string $class)
    {
        parent::__construct(sprintf(
            'The header name "%s" is already used by "%s" and cannot be used by "%s".',
            $headerName,
            $registeredClass,
            $class,
        ));
    }
}
