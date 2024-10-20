<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use DateTimeInterface;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidInterface;

/** @covers \Patchlevel\EventSourcing\Aggregate\Uuid */
final class UuidTest extends TestCase
{
    public function testFromString(): void
    {
        $id = Uuid::fromString('1eec1e5c-e397-6644-9aed-0242ac110002');

        self::assertSame('1eec1e5c-e397-6644-9aed-0242ac110002', $id->toString());
    }

    public function testGenerate(): void
    {
        $factory = new class extends UuidFactory
        {
            public function uuid7(DateTimeInterface|null $dateTime = null): UuidInterface
            {
                return RamseyUuid::fromString('018d6a97-6aba-7104-825f-67313a77a2a4');
            }
        };

        RamseyUuid::setFactory($factory);
        $id = Uuid::generate();

        self::assertSame('018d6a97-6aba-7104-825f-67313a77a2a4', $id->toString());
    }
}
