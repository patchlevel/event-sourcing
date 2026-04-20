<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use LogicException;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function sprintf;

/**
 * Normalizes a configuration array against a tree of nodes:
 * missing values are replaced by their defaults, unknown keys and invalid types are rejected.
 *
 * @internal
 *
 * @phpstan-type Node array<string, mixed>
 */
final class ConfigurationNormalizer
{
    /**
     * @param Node $node
     *
     * @throws InvalidConfiguration
     */
    public static function normalize(array $node, mixed $value, string $path): mixed
    {
        return self::normalizeNode($node, $value, true, $path);
    }

    /**
     * @param array<string, Node> $children
     *
     * @return Node
     */
    public static function struct(array $children, bool $omitMissing = false): array
    {
        return ['kind' => 'struct', 'children' => $children, 'omit_missing' => $omitMissing];
    }

    /**
     * A struct with an "enabled" key, which can also be configured with a bool.
     *
     * @param array<string, Node> $children
     *
     * @return Node
     */
    public static function toggle(bool $enabled, array $children): array
    {
        return ['kind' => 'toggle', 'enabled' => $enabled, 'children' => $children];
    }

    /** @return Node */
    public static function bool(bool|null $default): array
    {
        return ['kind' => 'bool', 'default' => $default];
    }

    /** @return Node */
    public static function int(int|null $default, int|null $min = null): array
    {
        return ['kind' => 'int', 'default' => $default, 'min' => $min];
    }

    /** @return Node */
    public static function float(float|null $default): array
    {
        return ['kind' => 'float', 'default' => $default];
    }

    /** @return Node */
    public static function string(string|null $default, bool $nullable = true): array
    {
        return ['kind' => 'string', 'default' => $default, 'nullable' => $nullable];
    }

    /** @return Node */
    public static function requiredString(): array
    {
        return ['kind' => 'string', 'default' => null, 'nullable' => false, 'required' => true];
    }

    /**
     * @param list<string> $values
     *
     * @return Node
     */
    public static function enum(array $values, string $default): array
    {
        return ['kind' => 'enum', 'values' => $values, 'default' => $default];
    }

    /**
     * @param list<string> $values
     *
     * @return Node
     */
    public static function requiredEnum(array $values): array
    {
        return ['kind' => 'enum', 'values' => $values, 'default' => null, 'required' => true];
    }

    /**
     * @param list<string> $default
     *
     * @return Node
     */
    public static function stringList(array $default = []): array
    {
        return ['kind' => 'list', 'item' => 'string', 'default' => $default];
    }

    /**
     * @param list<int> $default
     *
     * @return Node
     */
    public static function intList(array $default = []): array
    {
        return ['kind' => 'list', 'item' => 'int', 'default' => $default];
    }

    /**
     * A list of objects or service ids.
     *
     * @param class-string|null $class
     *
     * @return Node
     */
    public static function services(string|null $class = null): array
    {
        return ['kind' => 'list', 'item' => 'service', 'class' => $class, 'default' => []];
    }

    /**
     * @param Node                 $prototype
     * @param array<string, mixed> $default
     *
     * @return Node
     */
    public static function map(array $prototype, array $default = []): array
    {
        return ['kind' => 'map', 'prototype' => $prototype, 'default' => $default];
    }

    /**
     * @param class-string $class
     * @param Node         $node
     *
     * @return Node
     */
    public static function objectOr(string $class, array $node): array
    {
        return ['kind' => 'object_or', 'class' => $class, 'node' => $node];
    }

    /** @return Node */
    public static function object(): array
    {
        return ['kind' => 'object'];
    }

    /** @return Node */
    public static function variable(): array
    {
        return ['kind' => 'variable', 'default' => null];
    }

    /** @param Node $node */
    private static function normalizeNode(array $node, mixed $value, bool $present, string $path): mixed
    {
        if (!$present && ($node['required'] ?? false) === true) {
            throw new InvalidConfiguration(sprintf('The child config "%s" must be configured.', $path));
        }

        return match ($node['kind']) {
            'struct' => self::normalizeStruct($node, $present ? $value : [], $path),
            'toggle' => self::normalizeToggle($node, $value, $present, $path),
            'bool' => $present ? self::assertType($value, 'bool', is_bool($value) || ($value === null && $node['default'] === null), $path) : $node['default'],
            'int' => $present ? self::normalizeInt($node, $value, $path) : $node['default'],
            'float' => $present ? self::normalizeFloat($value, $path) : $node['default'],
            'string' => $present ? self::normalizeString($node, $value, $path) : $node['default'],
            'enum' => $present ? self::normalizeEnum($node, $value, $path) : $node['default'],
            'list' => $present ? self::normalizeList($node, $value, $path) : $node['default'],
            'map' => $present ? self::normalizeMap($node, $value, $path) : $node['default'],
            'object_or' => self::normalizeObjectOr($node, $value, $path),
            'object' => self::assertType($value, 'object', is_object($value), $path),
            'variable' => $present ? $value : $node['default'],
            default => throw new LogicException('Unknown configuration node.'),
        };
    }

    /**
     * @param Node $node
     *
     * @return array<string, mixed>
     */
    private static function normalizeStruct(array $node, mixed $value, string $path): array
    {
        if ($value === null) {
            $value = [];
        }

        if (!is_array($value)) {
            self::invalidType($value, 'array', $path);
        }

        /** @var array<string, Node> $children */
        $children = $node['children'];

        foreach (array_keys($value) as $key) {
            if (is_string($key) && array_key_exists($key, $children)) {
                continue;
            }

            throw new InvalidConfiguration(sprintf(
                'Unrecognized option "%s" under "%s". Available options are "%s".',
                $key,
                $path,
                implode('", "', array_keys($children)),
            ));
        }

        $result = [];

        foreach ($children as $key => $child) {
            $present = array_key_exists($key, $value);

            if (!$present && $node['omit_missing'] === true) {
                continue;
            }

            $result[$key] = self::normalizeNode($child, $present ? $value[$key] : null, $present, $path . '.' . $key);
        }

        return $result;
    }

    /**
     * @param Node $node
     *
     * @return array<string, mixed>
     */
    private static function normalizeToggle(array $node, mixed $value, bool $present, string $path): array
    {
        if (!$present) {
            $value = ['enabled' => $node['enabled']];
        }

        if (is_bool($value)) {
            $value = ['enabled' => $value];
        }

        if (!is_array($value)) {
            self::invalidType($value, 'bool" or "array', $path);
        }

        $enabled = $value['enabled'] ?? true;

        if (!is_bool($enabled)) {
            self::invalidType($enabled, 'bool', $path . '.enabled');
        }

        unset($value['enabled']);

        /** @var array<string, Node> $children */
        $children = $node['children'];

        return [
            'enabled' => $enabled,
            ...self::normalizeStruct(self::struct($children), $value, $path),
        ];
    }

    /** @param Node $node */
    private static function normalizeInt(array $node, mixed $value, string $path): int|null
    {
        if ($value === null && $node['default'] === null) {
            return null;
        }

        if (!is_int($value)) {
            self::invalidType($value, 'int', $path);
        }

        if (is_int($node['min']) && $value < $node['min']) {
            throw new InvalidConfiguration(sprintf(
                'The value %d is too small for path "%s". Should be greater than or equal to %d.',
                $value,
                $path,
                $node['min'],
            ));
        }

        return $value;
    }

    private static function normalizeFloat(mixed $value, string $path): float|null
    {
        if ($value === null || is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float)$value;
        }

        self::invalidType($value, 'float', $path);
    }

    /** @param Node $node */
    private static function normalizeString(array $node, mixed $value, string $path): string|null
    {
        if ($value === null && $node['nullable'] === true) {
            return null;
        }

        if (!is_string($value)) {
            self::invalidType($value, 'string', $path);
        }

        return $value;
    }

    /** @param Node $node */
    private static function normalizeEnum(array $node, mixed $value, string $path): string
    {
        /** @var list<string> $values */
        $values = $node['values'];

        if (!is_string($value) || !in_array($value, $values, true)) {
            throw new InvalidConfiguration(sprintf(
                'The value %s is not allowed for path "%s". Permissible values: "%s".',
                is_string($value) ? '"' . $value . '"' : get_debug_type($value),
                $path,
                implode('", "', $values),
            ));
        }

        return $value;
    }

    /**
     * @param Node $node
     *
     * @return list<mixed>
     */
    private static function normalizeList(array $node, mixed $value, string $path): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            self::invalidType($value, 'list', $path);
        }

        foreach ($value as $index => $item) {
            $itemPath = sprintf('%s.%d', $path, $index);

            match ($node['item']) {
                'string' => self::assertType($item, 'string', is_string($item), $itemPath),
                'int' => self::assertType($item, 'int', is_int($item), $itemPath),
                'service' => self::assertService($node, $item, $itemPath),
                default => throw new LogicException('Unknown configuration list item.'),
            };
        }

        return $value;
    }

    /**
     * @param Node $node
     *
     * @return array<string, mixed>
     */
    private static function normalizeMap(array $node, mixed $value, string $path): array
    {
        if (!is_array($value)) {
            self::invalidType($value, 'array', $path);
        }

        /** @var Node $prototype */
        $prototype = $node['prototype'];
        $result = [];

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new InvalidConfiguration(sprintf('The keys of "%s" must be strings, got "%s".', $path, $key));
            }

            $result[$key] = self::normalizeNode($prototype, $item, true, $path . '.' . $key);
        }

        return $result;
    }

    /** @param Node $node */
    private static function normalizeObjectOr(array $node, mixed $value, string $path): mixed
    {
        if (!is_object($value)) {
            /** @var Node $inner */
            $inner = $node['node'];

            return self::normalizeNode($inner, $value, true, $path);
        }

        /** @var class-string $class */
        $class = $node['class'];

        if (!$value instanceof $class) {
            self::invalidType($value, $class, $path);
        }

        return $value;
    }

    /** @param Node $node */
    private static function assertService(array $node, mixed $value, string $path): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        /** @var class-string|null $class */
        $class = $node['class'];

        if ($class === null) {
            return self::assertType($value, 'object" or "string', is_object($value), $path);
        }

        return self::assertType($value, $class . '" or "string', $value instanceof $class, $path);
    }

    private static function assertType(mixed $value, string $expected, bool $valid, string $path): mixed
    {
        if (!$valid) {
            self::invalidType($value, $expected, $path);
        }

        return $value;
    }

    private static function invalidType(mixed $value, string $expected, string $path): never
    {
        throw new InvalidConfiguration(sprintf(
            'Invalid type for path "%s". Expected "%s", but got "%s".',
            $path,
            $expected,
            get_debug_type($value),
        ));
    }
}
