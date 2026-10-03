<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootClassNotRegistered;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(AggregateRootClassNotRegistered::class)]
final class AggregateRootClassNotRegisteredTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateRootClassNotRegistered(Profile::class);

        self::assertSame(
            sprintf('Aggregate root class "%s" is not registered', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
