<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

interface Projector extends Projection
{
    public function projectorId(): ProjectorId;
}
