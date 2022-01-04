<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @template-extends AggregateChanged<array{id: string, name: string}>
 */
final class ProfileCreated extends AggregateChanged
{
    public static function raise(string $id, string $name): static
    {
        return new static($id, ['id' => $id, 'name' => $name]);
    }

    public function profileId(): string
    {
        return $this->aggregateId;
    }

    public function name(): string
    {
        return $this->payload['name'];
    }
}
