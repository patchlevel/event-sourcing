<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use function is_bool;
use function is_string;

final class InputHelper
{
    public static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentGiven($value, 'string|null');
        }

        return $value;
    }

    public static function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentGiven($value, 'string');
        }

        return $value;
    }

    public static function bool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentGiven($value, 'boolean');
        }

        return $value;
    }
}
