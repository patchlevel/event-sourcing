<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\Repository\AggregateUnknown;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(AggregateUnknown::class)]
final class AggregateUnknownTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateUnknown(Profile::class, ProfileId::fromString('1'));

        self::assertSame(
            sprintf('The aggregate %s with the ID "1" was not loaded from this repository. Please reload the aggregate.', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
