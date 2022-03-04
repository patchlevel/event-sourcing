<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

interface EventBus
{
    public function dispatch(Message $message): void;
}
