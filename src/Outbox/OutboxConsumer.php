<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

interface OutboxConsumer
{
    public function consume(?int $limit = null): void;
}
