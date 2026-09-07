<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\Repository\InvalidAggregate;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

use function sprintf;

#[CoversClass(InvalidAggregate::class)]
final class InvalidAggregateTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new InvalidAggregate('initialize', Profile::class, new stdClass());

        self::assertSame(
            sprintf('The method "initialize" in "%s" returned "stdClass". Expected an instance of "%s".', Profile::class, Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
