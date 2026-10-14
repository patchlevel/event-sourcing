<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use function array_fill_keys;
use function count;

/**
 * The loaded part of the inbox of a partition: the indices of the messages the partition has to process.
 *
 * @internal
 */
final class PartitionInbox
{
    /** @var array<int, true> */
    private readonly array $lookup;

    /** @param list<int> $indices in ascending order */
    public function __construct(
        private readonly array $indices,
    ) {
        $this->lookup = array_fill_keys($indices, true);
    }

    /** @return list<int> */
    public function indices(): array
    {
        return $this->indices;
    }

    public function contains(int $index): bool
    {
        return isset($this->lookup[$index]);
    }

    public function isEmpty(): bool
    {
        return $this->indices === [];
    }

    public function count(): int
    {
        return count($this->indices);
    }

    public function first(): int
    {
        return $this->indices[0] ?? throw new UnexpectedError('The inbox is empty.');
    }

    public function last(): int
    {
        return $this->indices[count($this->indices) - 1] ?? throw new UnexpectedError('The inbox is empty.');
    }
}
