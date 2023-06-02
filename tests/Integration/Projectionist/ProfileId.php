<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist;

final class ProfileId
{
    private function __construct(
        private string $id,
    ) {
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function toString(): string
    {
        return $this->id;
    }
}
