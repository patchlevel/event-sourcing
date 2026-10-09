<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\Repository\AggregateDetached;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(AggregateDetached::class)]
final class AggregateDetachedTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateDetached(Profile::class, ProfileId::fromString('1'));

        self::assertSame(
            sprintf('An error occurred while saving the aggregate "%s" with the ID "1", causing the uncommitted events to be lost. Please reload the aggregate.', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
