<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projection;

/**
 * @readonly
 */
final class ProjectionHandleMetadata
{
    public function __construct(
        public string $methodName,
        public bool $passMessage = false
    ) {
    }
}
