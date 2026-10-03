<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Identifier\Identifier;

final class ProfileId implements Identifier
{
    private function __construct(
        private string $id,
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
