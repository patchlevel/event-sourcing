<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup;

use Patchlevel\EventSourcing\Subscription\Subscription;

interface Cleaner
{
    public function cleanup(Subscription $subscription): void;
}
