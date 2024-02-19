<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;

#[Attribute(Attribute::TARGET_CLASS)]
final class Projector
{
    public function __construct(
        public readonly string $id,
        public readonly string $group = Projection::DEFAULT_GROUP,
        public readonly RunMode $runMode = RunMode::FromBeginning,
    ) {
    }
}
