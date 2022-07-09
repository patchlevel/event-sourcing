<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Snapshot
{
    private string $name;
    private ?int $batch;
    private ?string $version;

    public function __construct(string $name, ?int $batch = null, ?string $version = null)
    {
        $this->name = $name;
        $this->batch = $batch;
        $this->version = $version;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function batch(): ?int
    {
        return $this->batch;
    }

    public function version(): ?string
    {
        return $this->version;
    }
}
