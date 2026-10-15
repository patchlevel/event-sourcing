<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DecisionModel;

use RuntimeException;

/** @experimental */
final class DecisionAlreadyAppended extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The decision has already appended its events, start a new one.');
    }
}
