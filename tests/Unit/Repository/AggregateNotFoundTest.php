<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\Repository\AggregateNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(AggregateNotFound::class)]
final class AggregateNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateNotFound(Profile::class, ProfileId::fromString('1'));

        self::assertSame(
            sprintf('aggregate "%s::1" not found', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
