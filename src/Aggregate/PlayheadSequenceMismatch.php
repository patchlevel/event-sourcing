<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

final class PlayheadSequenceMismatch extends AggregateException
{
    public function __construct()
    {
        parent::__construct('The playhead sequence does not match the aggregate playhead.');
    }
}
