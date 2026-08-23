<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\Repository\WrongAggregate;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(WrongAggregate::class)]
final class WrongAggregateTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new WrongAggregate(Profile::class, ProfileWithSnapshot::class);

        self::assertSame(
            sprintf('Wrong aggregate given: got "%s" but expected "%s"', Profile::class, ProfileWithSnapshot::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
