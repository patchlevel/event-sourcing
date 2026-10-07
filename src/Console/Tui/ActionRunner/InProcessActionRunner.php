<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui\ActionRunner;

use Closure;
use Patchlevel\EventSourcing\Console\Tui\SubscriptionAction;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Throwable;

use function sprintf;

/**
 * Executes the actions directly with the subscription engine.
 * This blocks the dashboard until the action is done.
 *
 * @experimental
 */
final class InProcessActionRunner implements ActionRunner
{
    /** @param positive-int|null $messageLimit */
    public function __construct(
        private readonly SubscriptionEngine $engine,
        private readonly int|null $messageLimit = null,
    ) {
    }

    /**
     * @param list<string>                $ids
     * @param Closure(list<string>): void $onFinish
     */
    public function run(SubscriptionAction $action, array $ids, Closure $onFinish): void
    {
        $errors = [];

        try {
            foreach ($action->commands($ids, $this->messageLimit) as $command) {
                foreach ($this->engine->execute($command)->errors as $error) {
                    $errors[] = sprintf('%s: %s', $error->subscriptionId, $error->message);
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        $onFinish($errors);
    }
}
