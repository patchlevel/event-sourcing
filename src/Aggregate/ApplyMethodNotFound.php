<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function sprintf;

final class ApplyMethodNotFound extends AggregateException
{
    /**
     * @param class-string<AggregateRoot> $aggregate
     * @param class-string                $event
     */
    public function __construct(string $aggregate, string $event)
    {
        parent::__construct(
            sprintf(
                'Apply method in "%s" could not be found for the event "%s"',
                $aggregate,
                $event,
            ),
        );
    }
}
