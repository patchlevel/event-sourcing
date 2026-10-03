<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

/**
 * Marks an apply method of a projection whose event contains the whole state the projection needs.
 * Events before the last of these events are not loaded anymore.
 *
 * @experimental
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Checkpoint
{
}
