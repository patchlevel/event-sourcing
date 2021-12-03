<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function sprintf;

final class InvalidType extends StoreException
{
    public function __construct(string $field, string $type)
    {
        parent::__construct(sprintf('"%s" should be a "%s" type', $field, $type));
    }
}
