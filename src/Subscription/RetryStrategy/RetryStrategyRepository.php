<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\RetryStrategy;

final class RetryStrategyRepository
{
    public const DEFAULT_STRATEGY_NAME = 'default';

    /** @param array<string, RetryStrategy> $strategies */
    public function __construct(
        private readonly array $strategies,
        private readonly string $defaultStrategy = self::DEFAULT_STRATEGY_NAME,
    ) {
    }

    public function get(string $name): RetryStrategy
    {
        if (!isset($this->strategies[$name])) {
            throw new RetryStrategyNotFound($name);
        }

        return $this->strategies[$name];
    }

    public function getDefaultRetryStrategy(): RetryStrategy
    {
        return $this->get($this->defaultStrategy);
    }
}
