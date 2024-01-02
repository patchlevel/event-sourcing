<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

trait RamseyUuidBehaviour
{
    public function __construct(
        private readonly UuidInterface $id,
    ) {
    }

    public static function fromString(string $id): self
    {
        return new self(Uuid::fromString($id));
    }

    public function toString(): string
    {
        return $this->id->toString();
    }

    public static function v6(): self
    {
        return new self(Uuid::uuid6());
    }

    public static function v7(): self
    {
        return new self(Uuid::uuid7());
    }
}
