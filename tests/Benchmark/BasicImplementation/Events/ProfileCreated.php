<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class ProfileCreated extends AggregateChanged
{
    public static function raise(string $id, string $name): AggregateChanged
    {
        return self::occur($id, ['id' => $id, 'name' => $name]);
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
