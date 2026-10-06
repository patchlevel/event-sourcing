<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use LogicException;

use function array_key_exists;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function preg_replace_callback;
use function sprintf;
use function str_contains;
use function str_repeat;
use function str_starts_with;
use function strcasecmp;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;
use function uksort;

use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

/**
 * Generates the phpstan array shapes of the configuration out of the configuration tree
 * and writes them as "@phpstan-type" tags into the docblock of the class.
 *
 * For every named node a type alias is generated. If the configured (input) and the normalized type differ,
 * the normalized one is prefixed with "Normalized". The description and all other tags of the docblock are kept.
 *
 * The imports of the file are reused. Missing classes are imported, a class whose short name is already taken
 * is imported with the previous namespace segment as prefix, e.g. "QueryBusHandlerProvider".
 *
 * @internal
 *
 * @phpstan-import-type Node from ConfigurationNormalizer
 */
final class ConfigurationShapeGenerator
{
    private const INPUT = 'input';
    private const NORMALIZED = 'normalized';

    private const INDENT = '    ';
    private const MAX_INLINE_LENGTH = 80;

    private string $namespace = '';

    /** @var array<string, string> */
    private array $aliases = [];

    /**
     * class => name used in the file
     *
     * @var array<string, string>
     */
    private array $imports = [];

    /**
     * Returns the source with the generated array shapes.
     *
     * @param Node $tree
     */
    public function generate(array $tree, string $source): string
    {
        $this->namespace = preg_match('/^namespace ([^;]+);$/m', $source, $match) === 1 ? $match[1] : '';
        $this->aliases = [];
        $this->imports = $this->parseImports($source);

        $imports = $this->imports;

        $this->alias($tree);

        $source = $this->replaceDocBlock($source);

        // only touch the imports if classes were added, so the use block stays as it is otherwise
        if ($this->imports === $imports) {
            return $source;
        }

        return $this->replaceImports($source);
    }

    /** @return array<string, string> */
    private function parseImports(string $source): array
    {
        preg_match_all('/^use (?!function |const )([^;\s]+)(?: as (\w+))?;$/m', $source, $matches, PREG_SET_ORDER);

        $imports = [];

        foreach ($matches as $match) {
            $imports[$match[1]] = ($match[2] ?? '') !== '' ? $match[2] : $this->shortName($match[1]);
        }

        return $imports;
    }

    private function replaceDocBlock(string $source): string
    {
        if (preg_match('/\n(final )?class \w+/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new LogicException('No class found.');
        }

        $head = substr($source, 0, $match[0][1]);
        $start = strrpos($head, '/**');
        $end = strrpos($head, '*/');

        if ($start === false || $end === false || $end < $start) {
            throw new LogicException('The class has no docblock.');
        }

        return substr($source, 0, $start)
            . $this->docBlock(substr($source, $start, $end + 2 - $start))
            . substr($source, $end + 2);
    }

    /**
     * Keeps the description and all tags except "@phpstan-type" and puts the generated type aliases in front of them.
     */
    private function docBlock(string $docBlock): string
    {
        $description = [];
        $tags = [];

        foreach (array_slice(explode("\n", $docBlock), 1, -1) as $line) {
            $content = substr(ltrim($line), 2);

            if (str_starts_with($content, '@')) {
                $tags[] = [$content];

                continue;
            }

            if ($tags === []) {
                $description[] = $content;

                continue;
            }

            $tags[count($tags) - 1][] = $content;
        }

        while ($description !== [] && trim($description[count($description) - 1]) === '') {
            unset($description[count($description) - 1]);
        }

        $lines = array_values($description);

        if ($lines !== []) {
            $lines[] = '';
        }

        foreach ($this->aliases as $name => $type) {
            $lines = [...$lines, ...explode("\n", sprintf('@phpstan-type %s %s', $name, $type))];
        }

        foreach ($tags as $tag) {
            if (str_starts_with($tag[0], '@phpstan-type ')) {
                continue;
            }

            $lines = [...$lines, ...$tag];
        }

        return sprintf("/**\n%s\n */", implode("\n", array_map(
            static fn (string $line): string => $line === '' ? ' *' : ' * ' . $line,
            $lines,
        )));
    }

    private function replaceImports(string $source): string
    {
        $imports = $this->imports;
        uksort($imports, static fn (string $a, string $b): int => strcasecmp($a, $b));

        $uses = [];

        foreach ($imports as $class => $name) {
            $uses[] = $this->shortName($class) === $name
                ? sprintf('use %s;', $class)
                : sprintf('use %s as %s;', $class, $name);
        }

        $block = implode("\n", $uses);

        preg_match_all('/^use (?!function |const )[^;]+;$/m', $source, $matches, PREG_OFFSET_CAPTURE);

        if ($matches[0] === []) {
            $position = (int)strpos($source, "\n", (int)strpos($source, 'namespace ')) + 1;

            return substr($source, 0, $position) . "\n" . $block . "\n" . substr($source, $position);
        }

        $first = $matches[0][0][1];
        $last = $matches[0][count($matches[0]) - 1];

        return substr($source, 0, $first) . $block . substr($source, $last[1] + strlen($last[0]));
    }

    /**
     * Registers the type aliases of a named node and returns the alias for the requested mode.
     *
     * @param Node $node
     */
    private function alias(array $node, string $mode = self::INPUT): string
    {
        $name = $node['name'] ?? null;

        if (!is_string($name)) {
            throw new LogicException('Only named nodes have an alias.');
        }

        unset($node['name']);

        $normalizedName = 'Normalized' . $name;

        if (!array_key_exists($name, $this->aliases)) {
            $input = $this->type($node, self::INPUT, 0);
            $normalized = $this->type($node, self::NORMALIZED, 0);

            $this->aliases[$name] = $input;

            if ($input !== $normalized) {
                $this->aliases[$normalizedName] = $normalized;
            }
        }

        if ($mode === self::NORMALIZED && array_key_exists($normalizedName, $this->aliases)) {
            return $normalizedName;
        }

        return $name;
    }

    /** @param Node $node */
    private function type(array $node, string $mode, int $level): string
    {
        if (array_key_exists('name', $node)) {
            return $this->alias($node, $mode);
        }

        if (is_string($node['type'] ?? null)) {
            return $this->importClasses($node['type']);
        }

        return match ($node['kind']) {
            'struct' => $this->struct($this->nodes($node, 'children'), $mode, $level, $node['omit_missing'] === true),
            'toggle' => $this->toggle($this->nodes($node, 'children'), $mode, $level),
            'bool' => $this->nullable('bool', $node),
            'int' => $this->nullable($this->int($node['min']), $node),
            'float' => $this->nullable('float', $node),
            'string' => $this->nullable('string', $node),
            'enum' => $this->enum($node['values']),
            'list' => $this->list($node),
            'map' => sprintf('array<string, %s>', $this->type($this->node($node, 'prototype'), $mode, $level)),
            'object_or' => sprintf(
                '%s|%s',
                $this->import($this->className($node)),
                $this->type($this->node($node, 'node'), $mode, $level),
            ),
            'object' => 'object',
            'variable' => 'mixed',
            default => throw new LogicException('Unknown configuration node.'),
        };
    }

    /** @param array<string, Node> $children */
    private function struct(array $children, string $mode, int $level, bool $omitMissing): string
    {
        $entries = [];

        foreach ($children as $key => $child) {
            $optional = $mode === self::INPUT
                ? ($child['required'] ?? false) !== true
                : $omitMissing;

            $entries[] = sprintf('%s%s: %s', $key, $optional ? '?' : '', $this->type($child, $mode, $level + 1));
        }

        return $this->shape($entries, $level);
    }

    /** @param array<string, Node> $children */
    private function toggle(array $children, string $mode, int $level): string
    {
        $entries = [$mode === self::INPUT ? 'enabled?: bool' : 'enabled: bool'];

        foreach ($children as $key => $child) {
            $optional = $mode === self::INPUT && ($child['required'] ?? false) !== true;

            $entries[] = sprintf('%s%s: %s', $key, $optional ? '?' : '', $this->type($child, $mode, $level + 1));
        }

        $shape = $this->shape($entries, $level);

        return $mode === self::INPUT ? 'bool|' . $shape : $shape;
    }

    /** @param list<string> $entries */
    private function shape(array $entries, int $level): string
    {
        $inline = sprintf('array{%s}', implode(', ', $entries));

        if (!str_contains($inline, "\n") && strlen($inline) <= self::MAX_INLINE_LENGTH) {
            return $inline;
        }

        $indent = str_repeat(self::INDENT, $level + 1);
        $lines = array_map(static fn (string $entry): string => $indent . $entry . ',', $entries);

        return sprintf("array{\n%s\n%s}", implode("\n", $lines), str_repeat(self::INDENT, $level));
    }

    private function int(mixed $min): string
    {
        if ($min === null) {
            return 'int';
        }

        if ($min === 1) {
            return 'positive-int';
        }

        if (!is_int($min)) {
            throw new LogicException('The min value of an int node has to be an int.');
        }

        return sprintf('int<%d, max>', $min);
    }

    /** @param Node $node */
    private function nullable(string $type, array $node): string
    {
        return ($node['nullable'] ?? false) === true ? $type . '|null' : $type;
    }

    private function enum(mixed $values): string
    {
        if (!is_array($values)) {
            throw new LogicException('The values of an enum node have to be a list.');
        }

        return implode('|', array_map(
            static fn (mixed $value): string => is_string($value)
                ? sprintf("'%s'", $value)
                : throw new LogicException('The values of an enum node have to be strings.'),
            $values,
        ));
    }

    /** @param Node $node */
    private function list(array $node): string
    {
        return match ($node['item']) {
            'string' => 'list<string>',
            'int' => 'list<int>',
            'service' => is_string($node['class'])
                ? sprintf('list<%s|string>', $this->import($node['class']))
                : 'list<object|string>',
            default => throw new LogicException('Unknown configuration list item.'),
        };
    }

    /**
     * Replaces the fully qualified class names in a type with the names used in the file.
     */
    private function importClasses(string $type): string
    {
        return (string)preg_replace_callback(
            '/\\\\[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*/',
            fn (array $match): string => $this->import(ltrim($match[0], '\\')),
            $type,
        );
    }

    /**
     * Imports the class if needed and returns the name to reference it with.
     */
    private function import(string $class): string
    {
        if (array_key_exists($class, $this->imports)) {
            return $this->imports[$class];
        }

        $shortName = $this->shortName($class);

        if ($this->classNamespace($class) === $this->namespace) {
            return $shortName;
        }

        if (in_array($shortName, $this->imports, true)) {
            $segments = explode('\\', $class);
            $shortName = $segments[count($segments) - 2] . $shortName;
        }

        $this->imports[$class] = $shortName;

        return $shortName;
    }

    private function shortName(string $class): string
    {
        $segments = explode('\\', $class);

        return $segments[count($segments) - 1];
    }

    private function classNamespace(string $class): string
    {
        $segments = explode('\\', $class);

        return implode('\\', array_slice($segments, 0, -1));
    }

    /** @param Node $node */
    private function className(array $node): string
    {
        $class = $node['class'] ?? null;

        if (!is_string($class)) {
            throw new LogicException('The node has no class.');
        }

        return $class;
    }

    /**
     * @param Node $node
     *
     * @return Node
     */
    private function node(array $node, string $key): array
    {
        $child = $node[$key] ?? null;

        if (!is_array($child)) {
            throw new LogicException(sprintf('The node has no "%s".', $key));
        }

        /** @var Node $result */
        $result = $child;

        return $result;
    }

    /**
     * @param Node $node
     *
     * @return array<string, Node>
     */
    private function nodes(array $node, string $key): array
    {
        $children = $node[$key] ?? null;

        if (!is_array($children)) {
            throw new LogicException(sprintf('The node has no "%s".', $key));
        }

        /** @var array<string, Node> $result */
        $result = $children;

        return $result;
    }
}
