<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\ApplyMethodDetectionError;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\IncrementProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(ApplyMethodDetectionError::class)]
final class ApplyMethodDetectionErrorTest extends TestCase
{
    public function testDuplicateEmptyApplyAttribute(): void
    {
        $exception = ApplyMethodDetectionError::duplicateEmptyApplyAttribute('applyEvent');

        self::assertSame(
            'The method "applyEvent" has multiple #[Apply] attributes with an empty "event" argument. Only one is allowed.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testMixedApplyAttributeUsage(): void
    {
        $exception = ApplyMethodDetectionError::mixedApplyAttributeUsage('applyEvent');

        self::assertSame(
            'The method "applyEvent" has an #[Apply] attribute with an empty "event" argument, but also has a non-empty "event" argument in another #[Apply] attribute. This is not allowed.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testArgumentTypeIsNotAClass(): void
    {
        $exception = ApplyMethodDetectionError::argumentTypeIsNotAClass('applyEvent', 'foo');

        self::assertSame(
            'The method "applyEvent" has an #[Apply] attribute with an "event" argument that is not a class: string.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testDuplicateApplyMethod(): void
    {
        $exception = ApplyMethodDetectionError::duplicateApplyMethod(
            IncrementProjection::class,
            ProfileCreated::class,
            'applyA',
            'applyB',
        );

        self::assertSame(
            sprintf(
                'Two methods "applyA" and "applyB" on the class "%s" want to apply the same event "%s". Only one method can apply an event.',
                IncrementProjection::class,
                ProfileCreated::class,
            ),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testParameterIsMissing(): void
    {
        $exception = ApplyMethodDetectionError::parameterIsMissing('applyEvent', 1);

        self::assertSame(
            'The method "applyEvent" has an #[Apply] attribute with an "event" argument, but the parameter #1 is missing.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testArgumentTypeIsMissing(): void
    {
        $exception = ApplyMethodDetectionError::argumentTypeIsMissing('applyEvent');

        self::assertSame(
            'The method "applyEvent" has an #[Apply] attribute with an empty "event" argument. This is not allowed.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
