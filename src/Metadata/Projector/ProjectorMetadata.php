<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

final class ProjectorMetadata
{
    public function __construct(
        /** @var array<class-string, string> */
        public readonly array $subscribeMethods = [],
        public readonly string|null $createMethod = null,
        public readonly string|null $dropMethod = null,
    ) {
    }
}
