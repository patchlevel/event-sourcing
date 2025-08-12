<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\SubQuery;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function class_exists;

/** @experimental */
trait EventRouter
{
    /** @param array<class-string, string>|null $applyMethods */
    private array|null $applyMethods = null;

    public function apply(mixed $state, Message $message): mixed
    {
        if (!$this->subQuery()->match($message)) {
            return $state;
        }

        $event = $message->event();
        $applyMethods = $this->applyMethods();

        if (array_key_exists($event::class, $applyMethods)) {
            return $this->{$applyMethods[$event::class]}($state, $event);
        }

        return $state;
    }

    public function subQuery(): SubQuery
    {
        return new SubQuery(
            $this->tagFilter(),
            $this->eventTypeFilter(),
        );
    }

    /** @return list<class-string> */
    public function eventTypeFilter(): array
    {
        return array_keys($this->applyMethods());
    }

    /** @return array<class-string, string> */
    private function applyMethods(): array
    {
        if ($this->applyMethods !== null) {
            return $this->applyMethods;
        }

        $reflector = new ReflectionClass($this);

        $this->applyMethods = [];

        foreach ($reflector->getMethods() as $method) {
            $attributes = $method->getAttributes(Apply::class);

            if ($attributes === []) {
                continue;
            }

            $eventClasses = [];
            $hasOneEmptyApply = false;
            $hasOneNonEmptyApply = false;

            foreach ($attributes as $attribute) {
                $applyAttribute = $attribute->newInstance();
                $eventClass = $applyAttribute->eventClass;

                if ($eventClass !== null) {
                    $hasOneNonEmptyApply = true;
                    $eventClasses[] = $eventClass;

                    continue;
                }

                if ($hasOneEmptyApply) {
                    throw new DuplicateEmptyApplyAttribute($method->getName());
                }

                $hasOneEmptyApply = true;
                $eventClasses = array_merge($eventClasses, $this->getEventClassesByPropertyTypes($method));
            }

            if ($hasOneEmptyApply && $hasOneNonEmptyApply) {
                throw new MixedApplyAttributeUsage($method->getName());
            }

            foreach ($eventClasses as $eventClass) {
                if (!class_exists($eventClass)) {
                    throw new ArgumentTypeIsNotAClass($method->getName(), $eventClass);
                }

                if (array_key_exists($eventClass, $this->applyMethods)) {
                    throw new DuplicateApplyMethod(
                        $eventClass,
                        $this->applyMethods[$eventClass],
                        $method->getName(),
                    );
                }

                $this->applyMethods[$eventClass] = $method->getName();
            }
        }

        return $this->applyMethods;
    }

    /** @return array<string> */
    private function getEventClassesByPropertyTypes(ReflectionMethod $method): array
    {
        $parameters = $method->getParameters();

        if (array_key_exists(1, $parameters) === false) {
            throw new ParameterIsMissing($method->getName(), 1);
        }

        $propertyType = $parameters[1]->getType();
        $methodName = $method->getName();

        if ($propertyType === null) {
            throw new ArgumentTypeIsMissing($methodName);
        }

        if ($propertyType instanceof ReflectionIntersectionType) {
            throw new ArgumentTypeIsMissing($methodName);
        }

        if ($propertyType instanceof ReflectionNamedType) {
            return [$propertyType->getName()];
        }

        if ($propertyType instanceof ReflectionUnionType) {
            return array_map(
                static function (ReflectionNamedType|ReflectionIntersectionType $reflectionType) use ($methodName,
                ): string {
                    if ($reflectionType instanceof ReflectionIntersectionType) {
                        throw new ArgumentTypeIsMissing($methodName);
                    }

                    return $reflectionType->getName();
                },
                $propertyType->getTypes(),
            );
        }

        return [];
    }

    /** @return list<string> */
    abstract public function tagFilter(): array;
}
