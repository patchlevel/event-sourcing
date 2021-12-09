<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Pipeline\EventBucket;

final class UntilEventMiddleware implements Middleware
{
    private DateTimeImmutable $until;

    public function __construct(DateTimeImmutable $until)
    {
        $this->until = $until;
    }

    /**
     * @return list<EventBucket>
     */
    public function __invoke(EventBucket $bucket): array
    {
        $recordedOn = $bucket->event()->recordedOn();

        if ($recordedOn === null) {
            return [];
        }

        if ($recordedOn < $this->until) {
            return [$bucket];
        }

        return [];
    }
}
