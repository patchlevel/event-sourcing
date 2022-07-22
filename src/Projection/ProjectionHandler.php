<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\EventBus\Message;

/** @deprecated use Projectionist */
interface ProjectionHandler
{
    public function handle(Message $message): void;

    public function create(): void;

    public function drop(): void;
}
