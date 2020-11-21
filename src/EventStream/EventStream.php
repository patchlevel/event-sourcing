<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventStream;

interface EventStream
{
    public function dispatch(object $event): void;
}
