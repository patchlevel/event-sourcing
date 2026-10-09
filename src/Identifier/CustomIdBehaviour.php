<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Identifier;

trait CustomIdBehaviour
{
    public function __construct(
        private readonly string $id,
    ) {
    }

    public static function fromString(string $id): static
    {
        return new self($id);
    }

    public function toString(): string
    {
        return $this->id;
    }
}
