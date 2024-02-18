<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\EventBus\Header;
use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class NotAHeaderClass extends MetadataException
{
    /** @param class-string $headerClass */
    public function __construct(string $headerClass)
    {
        parent::__construct(sprintf('The class "%s" does not implement %s', $headerClass, Header::class));
    }
}
