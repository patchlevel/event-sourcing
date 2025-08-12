<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing;

use Patchlevel\EventSourcing\Serializer\Normalizer\StringableNormalizer;

#[StringableNormalizer]
interface Stringable
{
    public function toString(): string;

    public static function fromString(string $value): self;
}
