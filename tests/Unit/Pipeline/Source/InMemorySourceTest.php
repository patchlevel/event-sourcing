<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Source;

use ArrayIterator;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Source\InMemorySource;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Source\InMemorySource */
final class InMemorySourceTest extends TestCase
{
    public function testLoad(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );

        $source = new InMemorySource([$message]);

        $generator = $source->load();

        self::assertSame($message, $generator->current());

        $generator->next();

        self::assertSame(null, $generator->current());
    }

    public function testCount(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );

        $source = new InMemorySource([$message]);

        self::assertSame(1, $source->count());
    }

    public function testCountWithIterator(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );

        $source = new InMemorySource(new ArrayIterator([$message]));

        self::assertSame(1, $source->count());
    }
}
