<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use RuntimeException;

final class AlreadyProcessing extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Subscription engine is already processing');
    }
}
