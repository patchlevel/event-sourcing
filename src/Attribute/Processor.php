<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Attribute(Attribute::TARGET_CLASS)]
final class Processor extends Subscriber
{
    public function __construct(
        string $id,
        string $group = 'processor',
        RunMode $runMode = RunMode::FromNow,
        bool $batching = false,
    ) {
        parent::__construct($id, $runMode, $group, $batching);
    }
}
