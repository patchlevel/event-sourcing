<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use ArrayAccess;
use LogicException;
use OutOfBoundsException;
use Patchlevel\EventSourcing\Store\AppendCondition;

use function array_key_exists;

/**
 * @experimental
 * @psalm-immutable
 * @implements ArrayAccess<string, mixed>
 */
final class DecisionModel implements ArrayAccess
{
    /** @param array<string, mixed> $state */
    public function __construct(
        public readonly array $state,
        public readonly AppendCondition $appendCondition,
    ) {
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->state);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!$this->offsetExists($offset)) {
            throw new OutOfBoundsException("Offset '$offset' does not exist in the state.");
        }

        return $this->state[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('State is immutable, cannot set value.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('State is immutable, cannot unset value.');
    }
}
