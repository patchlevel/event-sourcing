<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projection;

final class ProjectionHandleMetadata
{
    public function __construct(
        public readonly string $methodName,
        public readonly bool $passMessage = false
    ) {
    }
}
