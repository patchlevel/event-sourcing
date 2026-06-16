<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

final class BatchMetadata
{
    public function __construct(
        public readonly string $flushMethod,
        public readonly string|null $beginMethod = null,
        public readonly string|null $shouldFlushMethod = null,
        public readonly string|null $rollbackMethod = null,
        public readonly int|null $afterMessages = null,
    ) {
    }
}
