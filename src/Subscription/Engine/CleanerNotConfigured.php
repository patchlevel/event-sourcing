<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use RuntimeException;

final class CleanerNotConfigured extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cleaner not configured.');
    }
}
