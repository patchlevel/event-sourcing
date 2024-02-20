<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\RetryStrategy;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Psr\Clock\ClockInterface;
use RuntimeException;

use function round;
use function sprintf;

final class DefaultRetryStrategy implements RetryStrategy
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

    public function nextAttempt(Retry|null $retry): Retry|null
    {
        if ($retry === null) {
            return new Retry(
                1,
                $this->calculateNextDate(1),
            );
        }

        if ($retry->attempt() >= $this->maxAttempts) {
            return null;
        }

        return new Retry(
            $retry->attempt() + 1,
            $this->calculateNextDate($retry->attempt()),
        );
    }

    public function shouldRetry(Retry|null $retry): bool
    {
        if ($retry === null) {
            return false;
        }

        $nextRetry = $retry->nextRetry();

        if ($nextRetry === null) {
            return false;
        }

        return $nextRetry <= $this->clock->now();
    }

    private function calculateNextDate(int $attempt): DateTimeImmutable
    {
        $nextDate = $this->clock->now()->modify(sprintf('+%d seconds', $this->calculateDelay($attempt)));

        if ($nextDate === false) {
            throw new RuntimeException('Could not calculate next date');
        }

        return $nextDate;
    }

    private function calculateDelay(int $attempt): int
    {
        return (int)round($this->baseDelay * ($this->delayFactor ** $attempt));
    }
}
