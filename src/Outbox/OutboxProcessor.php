<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

interface OutboxProcessor
{
    public function process(int|null $limit = null): void;
}
