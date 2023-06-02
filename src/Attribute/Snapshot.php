<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Snapshot
{
    public function __construct(private string $name, private int|null $batch = null, private string|null $version = null)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function batch(): int|null
    {
        return $this->batch;
    }

    public function version(): string|null
    {
        return $this->version;
    }
}
