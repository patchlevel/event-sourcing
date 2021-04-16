<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class NameChanged extends AggregateChanged
{
    public static function raise(string $id, string $name): AggregateChanged
    {
        return self::occur($id, ['name' => $name]);
    }

    public function name(): string
    {
        return $this->payload['name'];
    }
}
