<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function get_class;
use function sprintf;

final class ApplyMethodNotFound extends AggregateException
{
    public function __construct(AggregateRoot $aggregate, AggregateChanged $event, string $method)
    {
        parent::__construct(sprintf(
            'Apply method "%s::%s" could not be found for the event "%s"',
            get_class($aggregate),
            $method,
            get_class($event)
        ));
    }
}
