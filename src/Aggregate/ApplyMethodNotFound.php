<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function sprintf;

final class ApplyMethodNotFound extends AggregateException
{
    public function __construct(AggregateRoot $aggregate, AggregateChanged $event, string $method)
    {
        parent::__construct(sprintf(
            'Apply method "%s::%s" could not be found for the event "%s"',
            $aggregate::class,
            $method,
            $event::class
        ));
    }
}
