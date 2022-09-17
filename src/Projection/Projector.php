<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

interface Projector
{
    public function projectorId(): ProjectorId;
}
