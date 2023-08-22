<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

use function iterator_to_array;

/** @covers \Patchlevel\EventSourcing\Store\ArrayStream */
final class ArrayStreamTest extends TestCase
{
    use ProphecyTrait;

    public function testEmpty(): void
    {
        $stream = new ArrayStream();

        self::assertSame(null, $stream->position());
        self::assertSame(null, $stream->current());
        self::assertSame(null, $stream->index());

        $array = iterator_to_array($stream);

        self::assertSame([], $array);
    }

    public function testWithMessages(): void
    {
        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('foo'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $messages = [$message];

        $stream = new ArrayStream($messages);

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertSame($message, $stream->current());

        $array = iterator_to_array($stream);

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertSame(null, $stream->current());

        self::assertSame($messages, $array);
    }

    public function testPosition(): void
    {
        $messages = [
            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('foo'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('foo'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('foo'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
        ];

        $stream = new ArrayStream($messages);

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());

        iterator_to_array($stream);

        self::assertSame(3, $stream->index());
        self::assertSame(2, $stream->position());
    }
}
