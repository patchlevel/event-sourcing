<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

/**
 * Marks a parameter of a subscribe method that gets the partition key of the partition processing the message,
 * see {@see Partition}.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class PartitionKey
{
}
