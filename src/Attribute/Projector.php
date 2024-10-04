<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Attribute(Attribute::TARGET_CLASS)]
final class Projector extends Subscriber
{
    public function __construct(
        string $id,
        string $group = 'projector',
        RunMode $runMode = RunMode::FromBeginning,
        bool $batching = false,
    ) {
        parent::__construct($id, $runMode, $group, $batching);
    }
}
