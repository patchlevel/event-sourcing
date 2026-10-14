<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

/**
 * Marks the method that returns the partition key of a message, signature `(Message): string`.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Partition
{
}
