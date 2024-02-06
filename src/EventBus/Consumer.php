<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

interface Consumer
{
    public function consume(Message $message): void;
}
