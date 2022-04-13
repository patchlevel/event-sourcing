<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Snapshot
{
    private string $name;
    private ?int $batch;

    public function __construct(string $name, ?int $batch = null)
    {
        $this->name = $name;
        $this->batch = $batch;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function batch(): ?int
    {
        return $this->batch;
    }
}
