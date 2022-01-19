<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function sprintf;

final class ApplyAttributeNotFound extends AggregateException
{
    public function __construct(AggregateRoot $aggregate, AggregateChanged $event)
    {
        parent::__construct(
            sprintf(
                'Apply method in "%s" could not be found for the event "%s"',
                $aggregate::class,
                $event::class
            )
        );
    }
}
