<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

/**
 * @template-covariant T of array<string, mixed>
 */
abstract class AggregateChanged
{
    /**
     * @readonly
     * @var T
     */
    protected array $payload;

    /**
     * @param T $payload
     */
    final public function __construct(array $payload = [])
    {
        $this->payload = $payload;
    }

    /**
     * @return T
     */
    final public function payload(): array
    {
        return $this->payload;
    }
}
