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

/**
 * @experimental
 * @template S = mixed
 * @implements Projection<S>
 */
abstract class BasicProjection implements Projection, SubQueryProvider
{
    /** @var array<class-string, string>|null $applyMethods */
    private array|null $applyMethods = null;

    /**
     * @param S $state
     *
     * @return S
     */
    public function apply(mixed $state, Message $message): mixed
    {
        if (!$this->subQuery()->match($message)) {
            return $state;
        }

        $event = $message->event();
        $applyMethods = $this->applyMethods();

        if (array_key_exists($event::class, $applyMethods)) {
            /* @phpstan-ignore return.type */
            return $this->{$applyMethods[$event::class]}($state, $event);
        }

        return $state;
    }

    public function subQuery(): SubQuery
    {
        return new SubQuery(
            $this->tagFilter(),
            $this->eventTypeFilter(),
            $this->streamName(),
            $this->lastEventIsEnough(),
        );
    }

    /** @return list<class-string> */
    protected function eventTypeFilter(): array
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
                    throw ApplyMethodDetectionError::duplicateEmptyApplyAttribute(
                        $method->getName(),
                    );
                }

                $hasOneEmptyApply = true;
                $eventClasses = array_merge($eventClasses, $this->getEventClassesByPropertyTypes($method));
            }

            if ($hasOneEmptyApply && $hasOneNonEmptyApply) {
                throw ApplyMethodDetectionError::mixedApplyAttributeUsage(
                    $method->getName(),
                );
            }

            foreach ($eventClasses as $eventClass) {
                if (!class_exists($eventClass)) {
                    throw ApplyMethodDetectionError::argumentTypeIsNotAClass(
                        $method->getName(),
                        $eventClass,
                    );
                }

                if (array_key_exists($eventClass, $this->applyMethods)) {
                    throw ApplyMethodDetectionError::duplicateApplyMethod(
                        static::class,
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
            throw ApplyMethodDetectionError::parameterIsMissing($method->getName(), 1);
        }

        $propertyType = $parameters[1]->getType();
        $methodName = $method->getName();

        if ($propertyType === null) {
            throw ApplyMethodDetectionError::argumentTypeIsMissing($methodName);
        }

        if ($propertyType instanceof ReflectionIntersectionType) {
            throw ApplyMethodDetectionError::argumentTypeIsMissing($methodName);
        }

        if ($propertyType instanceof ReflectionNamedType) {
            return [$propertyType->getName()];
        }

        if ($propertyType instanceof ReflectionUnionType) {
            return array_map(
                static function (ReflectionNamedType|ReflectionIntersectionType $reflectionType) use ($methodName,
                ): string {
                    if ($reflectionType instanceof ReflectionIntersectionType) {
                        throw ApplyMethodDetectionError::argumentTypeIsMissing($methodName);
                    }

                    return $reflectionType->getName();
                },
                $propertyType->getTypes(),
            );
        }

        return [];
    }

    /** @return list<string> */
    abstract protected function tagFilter(): array;

    protected function streamName(): string|null
    {
        return null;
    }

    protected function lastEventIsEnough(): bool
    {
        return false;
    }
}
