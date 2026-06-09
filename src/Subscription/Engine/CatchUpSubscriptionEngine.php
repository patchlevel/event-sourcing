<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
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

    public function run(Command $command): Result
    {
        $mergedResult = new ProcessedResult(0);

        $catchupLimit = $this->limit ?? PHP_INT_MAX;

        for ($i = 0; $i < $catchupLimit; $i++) {
            $result = $this->parent->run($command);

            if (!$result instanceof ProcessedResult) {
                return $result;
            }

            $mergedResult = $this->mergeResult($mergedResult, $result);

            if ($result->processedMessages === 0) {
                break;
            }
        }

        return $mergedResult;
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        return $this->parent->subscriptions($criteria);
    }

    private function mergeResult(ProcessedResult ...$results): ProcessedResult
    {
        $processedMessages = 0;
        $finished = false;
        $errors = [];

        foreach ($results as $result) {
            $processedMessages += $result->processedMessages;
            $finished = $result->finished;
            $errors[] = $result->errors;
        }

        return new ProcessedResult(
            $processedMessages,
            $finished,
            array_merge(...$errors),
        );
    }
}
