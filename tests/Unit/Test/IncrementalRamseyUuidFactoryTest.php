<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Test;

use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Test\IncrementalRamseyUuidFactory;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

/** @covers \Patchlevel\EventSourcing\Test\IncrementalRamseyUuidFactory */
final class IncrementalRamseyUuidFactoryTest extends TestCase
{
    public function testGenerate(): void
    {
        $factory = new IncrementalRamseyUuidFactory();

        self::assertSame('10000000-7000-0000-0000-000000000001', $factory->uuid7()->toString());
        self::assertSame('10000000-7000-0000-0000-000000000002', $factory->uuid7()->toString());
        self::assertSame('10000000-7000-0000-0000-000000000003', $factory->uuid7()->toString());

        $factory->reset();

        self::assertSame('10000000-7000-0000-0000-000000000001', $factory->uuid7()->toString());
    }

    public function testUuid(): void
    {
        $previousFactory = RamseyUuid::getFactory();

        try {
            RamseyUuid::setFactory(new IncrementalRamseyUuidFactory());

            self::assertSame('10000000-7000-0000-0000-000000000001', Uuid::generate()->toString());
            self::assertSame('10000000-7000-0000-0000-000000000002', Uuid::generate()->toString());
            self::assertSame('10000000-7000-0000-0000-000000000003', Uuid::generate()->toString());
        } finally {
            RamseyUuid::setFactory($previousFactory);
        }
    }
}
