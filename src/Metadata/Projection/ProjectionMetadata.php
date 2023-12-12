<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projection;

final class ProjectionMetadata
{
    public function __construct(
        /** @var array<class-string, string> */
        public readonly array $handleMethods,
        public readonly string|null $createMethod = null,
        public readonly string|null $dropMethod = null,
    ) {
    }
}
