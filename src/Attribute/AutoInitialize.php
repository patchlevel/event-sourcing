<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

/** @experimental */
#[Attribute(Attribute::TARGET_METHOD)]
final class AutoInitialize
{
}
