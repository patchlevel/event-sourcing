<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\ClassIsNotAnAggregate;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(ClassIsNotAnAggregate::class)]
final class ClassIsNotAnAggregateTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ClassIsNotAnAggregate(Profile::class);

        self::assertSame(
            sprintf('class %s is not an aggregate root', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
