<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use DateInterval;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Clock\ClockInterface;

/** @deprecated use StoreMessageLoader with GapDetection instead */
final class GapResolverStoreMessageLoader implements MessageLoader
{
    private readonly StoreMessageLoader $loader;

    /** @param list<int> $retriesInMs in milliseconds */
    public function __construct(
        Store $store,
        ClockInterface $clock = new SystemClock(),
        array $retriesInMs = [0, 5, 50, 500],
        DateInterval|null $detectionWindow = new DateInterval('PT5M'),
    ) {
        $this->loader = new StoreMessageLoader(
            $store,
            gapDetection: new GapDetection($clock, $retriesInMs, $detectionWindow),
        );
    }

    /** @param list<Subscription> $subscriptions */
    public function load(int $startIndex, array $subscriptions): Stream
    {
        return $this->loader->load($startIndex, $subscriptions);
    }

    public function lastIndex(): int
    {
        return $this->loader->lastIndex();
    }
}
