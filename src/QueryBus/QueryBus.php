<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\QueryBus;

interface QueryBus
{
    public function dispatch(object $query): mixed;
}
