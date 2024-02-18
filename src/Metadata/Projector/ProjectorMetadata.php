<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

final class ProjectorMetadata
{
    public function __construct(
        public readonly string $id,
        public readonly string $group = 'default',
        public readonly bool $fromNow = false,
        /** @var array<class-string|"*", list<string>> */
        public readonly array $subscribeMethods = [],
        public readonly string|null $setupMethod = null,
        public readonly string|null $teardownMethod = null,
    ) {
    }
}
