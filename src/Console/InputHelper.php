<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use function is_bool;
use function is_int;
use function is_numeric;
use function is_string;

final class InputHelper
{
    public static function nullableString(mixed $value): string|null
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

    public static function int(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentGiven($value, 'int');
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentGiven($value, 'int');
        }

        return (int)$value;
    }

    public static function nullableInt(mixed $value): int|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentGiven($value, 'int|null');
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentGiven($value, 'int|null');
        }

        return (int)$value;
    }

    /** @return positive-int|null */
    public static function nullablePositivInt(mixed $value): int|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentGiven($value, 'positiv-int|null');
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentGiven($value, 'positiv-int|null');
        }

        $value = (int)$value;

        if ($value <= 0) {
            throw new InvalidArgumentGiven($value, 'positiv-int|null');
        }

        return $value;
    }

    public static function bool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentGiven($value, 'bool');
        }

        return $value;
    }
}
