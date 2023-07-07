<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Throwable;

use function sprintf;

final class MissingDataForStorage extends StoreException
{
    public function __construct(string $information, Throwable|null $previous = null)
    {
        parent::__construct(
            sprintf('Cannot save because the following information is missing: "%s"', $information),
            0,
            $previous,
        );
    }
}
