<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(ArgumentMetadata::class)]
final class ArgumentMetadataTest extends TestCase
{
    public function testHasAttributeReturnsTrueWhenPresent(): void
    {
        $argument = new ArgumentMetadata('state', Type::object(BatchState::class), [new BatchState()]);

        self::assertTrue($argument->hasAttribute(BatchState::class));
    }

    public function testHasAttributeReturnsFalseForDifferentAttribute(): void
    {
        $argument = new ArgumentMetadata('state', Type::object(BatchState::class), [new BatchState()]);

        self::assertFalse($argument->hasAttribute(Subscribe::class));
    }

    public function testHasAttributeReturnsFalseWithoutAttributes(): void
    {
        $argument = new ArgumentMetadata('event', Type::object(BatchState::class));

        self::assertFalse($argument->hasAttribute(BatchState::class));
    }
}
