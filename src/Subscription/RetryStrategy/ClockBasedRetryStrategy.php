<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\RetryStrategy;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Clock\ClockInterface;

use function round;
use function sprintf;

final class ClockBasedRetryStrategy implements ConditionalRetryStrategy, DelayRetryStrategy
{
    public const DEFAULT_BASE_DELAY = 5;
    public const DEFAULT_DELAY_FACTOR = 2;
    public const DEFAULT_MAX_ATTEMPTS = 5;

    /**
     * @param int          $baseDelay   in seconds
     * @param positive-int $maxAttempts
     */
    public function __construct(
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly int $baseDelay = self::DEFAULT_BASE_DELAY,
        private readonly float $delayFactor = self::DEFAULT_DELAY_FACTOR,
        private readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
    ) {
    }

    public function canRetry(Subscription $subscription): bool
    {
        return $subscription->retryAttempt() < $this->maxAttempts;
    }

    public function shouldRetry(Subscription $subscription): bool
    {
        if ($this->canRetry($subscription) === false) {
            return false;
        }

        return $this->delayUntil($subscription) <= $this->clock->now();
    }

    public function delayUntil(Subscription $subscription): DateTimeImmutable
    {
        $lastSavedAt = $subscription->lastSavedAt();

        if ($lastSavedAt === null) {
            $lastSavedAt = $this->clock->now();
        }

        $seconds = (int)round($this->baseDelay * ($this->delayFactor ** $subscription->retryAttempt()));

        $nextDate = $lastSavedAt->modify(sprintf('+%d seconds', $seconds));

        if ($nextDate === false) {
            throw new UnexpectedError('Could not calculate next date.');
        }

        return $nextDate;
    }
}
