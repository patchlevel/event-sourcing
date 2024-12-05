<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use DateInterval;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
final class Delay
{
    public readonly DateInterval $delay;

    public function __construct(
        string $dateString,
    ) {
        $interval = DateInterval::createFromDateString($dateString);

        if ($interval === false) {
            throw new InvalidArgumentException('Invalid date string');
        }

        $this->delay = $interval;
    }
}
