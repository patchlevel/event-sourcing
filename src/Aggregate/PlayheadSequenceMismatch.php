<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function sprintf;

final class PlayheadSequenceMismatch extends AggregateException
{
    public function __construct(string $aggregate)
    {
        parent::__construct(sprintf('The playhead sequence does not match the aggregate "%s" playhead.', $aggregate));
    }
}
