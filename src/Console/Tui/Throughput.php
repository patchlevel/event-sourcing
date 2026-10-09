<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui;

use DateTimeImmutable;

use function array_shift;
use function count;
use function in_array;

/**
 * Calculates messages per second from the positions seen on the last reloads.
 *
 * The rate is averaged over a sliding window, so it does not jump around with every reload.
 *
 * @experimental
 */
final class Throughput
{
    private const WINDOW_SECONDS = 10.0;

    /** @var array<string, list<array{float, int}>> */
    private array $samples = [];

    public function record(string $key, int|null $position, DateTimeImmutable $now): void
    {
        if ($position === null) {
            unset($this->samples[$key]);

            return;
        }

        $time = (float)$now->format('U.u');
        $samples = $this->samples[$key] ?? [];
        $last = $samples[count($samples) - 1] ?? null;

        // the position went back, e.g. after a rebuild, so the old samples are meaningless
        if ($last !== null && $position < $last[1]) {
            $samples = [];
        }

        $samples[] = [$time, $position];

        // keep one sample older than the window as starting point
        while (count($samples) > 2 && $samples[1][0] <= $time - self::WINDOW_SECONDS) {
            array_shift($samples);
        }

        $this->samples[$key] = $samples;
    }

    /** @param list<string> $keys */
    public function retain(array $keys): void
    {
        foreach ($this->samples as $key => $samples) {
            if (in_array((string)$key, $keys, true)) {
                continue;
            }

            unset($this->samples[$key]);
        }
    }

    public function rate(string $key): float|null
    {
        $samples = $this->samples[$key] ?? [];

        if (count($samples) < 2) {
            return null;
        }

        [$startTime, $startPosition] = $samples[0];
        [$endTime, $endPosition] = $samples[count($samples) - 1];

        if ($endTime <= $startTime) {
            return null;
        }

        return ($endPosition - $startPosition) / ($endTime - $startTime);
    }
}
