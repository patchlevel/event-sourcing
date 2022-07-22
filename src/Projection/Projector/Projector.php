<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

abstract class Projector
{
    public function projectorId(): ProjectorId
    {
        return new ProjectorId($this->name(), $this->version());
    }

    abstract public function name(): string;

    abstract public function version(): int;
}
