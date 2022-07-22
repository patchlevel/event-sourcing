<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

final class ProjectorMetadata
{
    public function __construct(
        /** @var array<class-string, string> */
        public readonly array $handleMethods = [],
        public readonly ?string $createMethod = null,
        public readonly ?string $dropMethod = null
    ) {
    }
}
