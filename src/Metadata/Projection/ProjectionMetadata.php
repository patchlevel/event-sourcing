<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projection;

/**
 * @readonly
 */
final class ProjectionMetadata
{
    public function __construct(
        /** @var array<class-string, ProjectionHandleMetadata> */
        public array $handleMethods,
        public ?string $createMethod = null,
        public ?string $dropMethod = null
    ) {
    }
}
