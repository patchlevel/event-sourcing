<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

use JsonSerializable;

/**
 * @psalm-immutable
 * @template T of array
 */
interface Header extends JsonSerializable
{
    public static function name(): string;

    /** @param T $data */
    public static function fromJsonSerialize(array $data): static;

    /** @return T */
    public function jsonSerialize(): array;
}
