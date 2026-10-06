<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

/** @experimental */
interface CheckpointProvider
{
    /**
     * Events which contain the whole state the projection needs.
     * Everything before the last of these events is not loaded anymore.
     *
     * @return list<class-string>
     */
    public function checkpointEvents(): array;
}
