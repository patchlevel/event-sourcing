<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use RuntimeException;

use function get_debug_type;
use function sprintf;

/** @experimental */
final class ApplyMethodDetectionError extends RuntimeException
{
    public static function duplicateEmptyApplyAttribute(string $methodName): self
    {
        return new self(
            sprintf(
                'The method "%s" has multiple #[Apply] attributes with an empty "event" argument. Only one is allowed.',
                $methodName,
            ),
        );
    }

    public static function mixedApplyAttributeUsage(string $methodName): self
    {
        return new self(
            sprintf(
                'The method "%s" has an #[Apply] attribute with an empty "event" argument, but also has a non-empty "event" argument in another #[Apply] attribute. This is not allowed.',
                $methodName,
            ),
        );
    }

    public static function argumentTypeIsNotAClass(string $methodName, mixed $value): self
    {
        return new self(
            sprintf(
                'The method "%s" has an #[Apply] attribute with an "event" argument that is not a class: %s.',
                $methodName,
                get_debug_type($value),
            ),
        );
    }

    /**
     * @param class-string $class
     * @param class-string $event
     */
    public static function duplicateApplyMethod(string $class, string $event, string $fistMethod, string $secondMethod): self
    {
        return new self(
            sprintf(
                'Two methods "%s" and "%s" on the class "%s" want to apply the same event "%s". Only one method can apply an event.',
                $fistMethod,
                $secondMethod,
                $class,
                $event,
            ),
        );
    }

    public static function parameterIsMissing(string $methodName, int $parameterNumber): self
    {
        return new self(
            sprintf(
                'The method "%s" has an #[Apply] attribute with an "event" argument, but the parameter #%d is missing.',
                $methodName,
                $parameterNumber,
            ),
        );
    }

    public static function argumentTypeIsMissing(string $methodName): self
    {
        return new self(
            sprintf(
                'The method "%s" has an #[Apply] attribute with an empty "event" argument. This is not allowed.',
                $methodName,
            ),
        );
    }
}
