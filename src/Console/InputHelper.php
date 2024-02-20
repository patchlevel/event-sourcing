<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use function array_map;
use function array_values;
use function is_array;
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
    public static function nullablePositiveInt(mixed $value): int|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentGiven($value, 'positive-int|null');
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentGiven($value, 'positive-int|null');
        }

        $value = (int)$value;

        if ($value <= 0) {
            throw new InvalidArgumentGiven($value, 'positive-int|null');
        }

        return $value;
    }

    /** @return positive-int|0 */
    public static function positiveIntOrZero(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentGiven($value, 'positive-int|0');
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentGiven($value, 'positive-int|0');
        }

        $value = (int)$value;

        if ($value < 0) {
            throw new InvalidArgumentGiven($value, 'positive-int|0');
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

    /** @return list<string>|null */
    public static function nullableStringList(mixed $value): array|null
    {
        if (!$value) {
            return null;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentGiven($value, 'list<string>|null');
        }

        return array_values(
            array_map(
                static function (mixed $string) use ($value): string {
                    if (!is_string($string)) {
                        throw new InvalidArgumentGiven($value, 'list<string>|null');
                    }

                    return $string;
                },
                $value,
            ),
        );
    }
}
