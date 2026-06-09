<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Subscription;

final class ThrowOnErrorSubscriptionEngine implements SubscriptionEngine
{
    public function __construct(
        private readonly SubscriptionEngine $parent,
    ) {
    }

    public function run(Command $command): Result
    {
        $result = $this->parent->run($command);
        $errors = $result->errors;

        if ($errors !== []) {
            throw new ErrorDetected($errors);
        }

        return $result;
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        return $this->parent->subscriptions($criteria);
    }
}
