<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Subscription;

use function array_merge;

use const PHP_INT_MAX;

final class CatchUpSubscriptionEngine implements SubscriptionEngine
{
    public function __construct(
        private readonly SubscriptionEngine $parent,
        private readonly int|null $limit = null,
    ) {
    }

    public function setup(SubscriptionEngineCriteria|null $criteria = null, bool $skipBooting = false): Result
    {
        return $this->parent->setup($criteria, $skipBooting);
    }

    public function boot(SubscriptionEngineCriteria|null $criteria = null, int|null $limit = null): ProcessedResult
    {
        $results = [];

        $catchupLimit = $this->limit ?? PHP_INT_MAX;

        for ($i = 0; $i < $catchupLimit; $i++) {
            $lastResult = $this->parent->boot($criteria, $limit);

            $results[] = $lastResult;

            if ($lastResult->processedMessages === 0) {
                break;
            }
        }

        return $this->mergeResult(...$results);
    }

    public function run(SubscriptionEngineCriteria|null $criteria = null, int|null $limit = null): ProcessedResult
    {
        $mergedResult = new ProcessedResult(0);

        $catchupLimit = $this->limit ?? PHP_INT_MAX;

        for ($i = 0; $i < $catchupLimit; $i++) {
            $result = $this->parent->run($criteria, $limit);
            $mergedResult = $this->mergeResult($mergedResult, $result);

            if ($result->processedMessages === 0) {
                break;
            }
        }

        return $mergedResult;
    }

    public function teardown(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->parent->teardown($criteria);
    }

    public function remove(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->parent->remove($criteria);
    }

    public function reactivate(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->parent->reactivate($criteria);
    }

    public function pause(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        return $this->parent->pause($criteria);
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        return $this->parent->subscriptions($criteria);
    }

    private function mergeResult(ProcessedResult ...$results): ProcessedResult
    {
        $processedMessages = 0;
        $streamFinished = false;
        $errors = [];

        foreach ($results as $result) {
            $processedMessages += $result->processedMessages;
            $streamFinished = $result->streamFinished;
            $errors[] = $result->errors;
        }

        return new ProcessedResult(
            $processedMessages,
            $streamFinished,
            array_merge(...$errors),
        );
    }
}
