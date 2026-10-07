<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui\ActionRunner;

use Closure;
use Patchlevel\EventSourcing\Console\Tui\SubscriptionAction;

/** @experimental */
interface ActionRunner
{
    /**
     * Executes the action for the given subscriptions and calls $onFinish with the error messages.
     * The callback may be called synchronously or later from the event loop.
     *
     * @param list<string>                $ids
     * @param Closure(list<string>): void $onFinish
     */
    public function run(SubscriptionAction $action, array $ids, Closure $onFinish): void;
}
