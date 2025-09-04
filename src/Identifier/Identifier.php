<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Identifier;

use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[IdNormalizer]
interface Identifier
{
    public function toString(): string;

    public static function fromString(string $id): static;
}
