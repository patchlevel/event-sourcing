<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

interface Listener
{
    public function __invoke(Message $message): void;
}
