<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Subscription;

final class ThrowOnErrorSubscriptionEngine implements SubscriptionEngine
{
    public function __construct(
        private readonly SubscriptionEngine $parent,
    ) {
    }

    public function setup(SubscriptionEngineCriteria|null $criteria = null, bool $skipBooting = false): Result
    {
        return $this->throwOnError($this->parent->setup($criteria, $skipBooting));
    }

    public function boot(SubscriptionEngineCriteria|null $criteria = null, int|null $limit = null): ProcessedResult
    {
        return $this->throwOnError($this->parent->boot($criteria, $limit));
    }

    public function run(SubscriptionEngineCriteria|null $criteria = null, int|null $limit = null): ProcessedResult
    {
        return $this->throwOnError($this->parent->run($criteria, $limit));
    }

    public function teardown(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->throwOnError($this->parent->teardown($criteria));
    }

    public function remove(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->throwOnError($this->parent->remove($criteria));
    }

    public function reactivate(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->throwOnError($this->parent->reactivate($criteria));
    }

    public function pause(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->throwOnError($this->parent->pause($criteria));
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        return $this->parent->subscriptions($criteria);
    }

    /**
     * @param T $result
     *
     * @return T
     *
     * @template T of Result|ProcessedResult
     */
    private function throwOnError(Result|ProcessedResult $result): Result|ProcessedResult
    {
        $errors = $result->errors;

        if ($errors !== []) {
            throw new ErrorDetected($errors);
        }

        return $result;
    }
}
