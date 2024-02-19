<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;

final class ProjectorMetadata
{
    public function __construct(
        public readonly string $id,
        public readonly string $group = Projection::DEFAULT_GROUP,
        public readonly RunMode $runMode = RunMode::FromBeginning,
        /** @var array<class-string|"*", list<string>> */
        public readonly array $subscribeMethods = [],
        public readonly string|null $setupMethod = null,
        public readonly string|null $teardownMethod = null,
    ) {
    }
}
