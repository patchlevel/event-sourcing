<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projection;

final class ProjectionMetadata
{
    public function __construct(
        /** @var array<class-string, ProjectionHandleMetadata> */
        public readonly array $handleMethods,
        public readonly ?string $createMethod = null,
        public readonly ?string $dropMethod = null
    ) {
    }
}
