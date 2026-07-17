<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\Repository\AggregateAlreadyExists;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(AggregateAlreadyExists::class)]
final class AggregateAlreadyExistsTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateAlreadyExists(Profile::class, ProfileId::fromString('1'));

        self::assertSame(
            sprintf('aggregate %s with id 1 already exists', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
