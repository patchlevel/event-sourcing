<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Identifier\Identifier;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Stringable;

use function array_keys;
use function array_map;
use function hash;
use function is_array;
use function is_int;
use function is_string;
use function strval;

/** @experimental */
final class AttributeEventTagExtractor implements EventTagExtractor
{
    /** @return list<string> */
    public function extract(object $event): array
    {
        $reflectionClass = new ReflectionClass($event);

        $tags = [];

        foreach ([...$reflectionClass->getProperties(), ...$reflectionClass->getMethods()] as $member) {
            $attributes = $member->getAttributes(EventTag::class);

            if ($attributes === []) {
                continue;
            }

            /** @var EventTag $attribute */
            $attribute = $attributes[0]->newInstance();

            $value = $this->value($event, $member);
            $values = is_array($value) ? $value : [$value];

            foreach ($values as $item) {
                $tag = $this->tag($event, $member, $item, $attribute->prefix, $attribute->hash);

                if ($tag === null) {
                    continue;
                }

                $tags[$tag] = true;
            }
        }

        return array_map(strval(...), array_keys($tags));
    }

    private function value(object $event, ReflectionProperty|ReflectionMethod $member): mixed
    {
        if ($member instanceof ReflectionProperty) {
            return $member->getValue($event);
        }

        if ($member->getNumberOfRequiredParameters() > 0) {
            throw EventTagExtractorError::methodHasRequiredParameters($event::class, $member->getName());
        }

        return $member->invoke($event);
    }

    private function tag(
        object $event,
        ReflectionProperty|ReflectionMethod $member,
        mixed $value,
        string|null $prefix,
        string|null $hash,
    ): string|null {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Stringable || is_int($value)) {
            $value = (string)$value;
        }

        if ($value instanceof Identifier) {
            $value = $value->toString();
        }

        if (!is_string($value)) {
            if ($member instanceof ReflectionMethod) {
                throw EventTagExtractorError::invalidMethodValueType($event::class, $member->getName(), $value);
            }

            throw EventTagExtractorError::invalidValueType($event::class, $member->getName(), $value);
        }

        if ($hash) {
            $value = hash($hash, $value);
        }

        if ($prefix) {
            $value = $prefix . ':' . $value;
        }

        return $value;
    }
}
