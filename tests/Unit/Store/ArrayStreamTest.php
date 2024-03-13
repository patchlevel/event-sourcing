<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Message\Message;
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
        self::assertSame(true, $stream->end());

        $array = iterator_to_array($stream);

        self::assertSame([], $array);
    }

    public function testOneMessage(): void
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
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertSame(null, $stream->current());
        self::assertSame(true, $stream->end());
    }

    public function testMultipleMessages(): void
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
        self::assertSame($messages[0], $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(2, $stream->index());
        self::assertSame(1, $stream->position());
        self::assertSame($messages[1], $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(3, $stream->index());
        self::assertSame(2, $stream->position());
        self::assertSame($messages[2], $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(3, $stream->index());
        self::assertSame(2, $stream->position());
        self::assertSame(null, $stream->current());
        self::assertSame(true, $stream->end());
    }

    public function testWithNoList(): void
    {
        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('foo'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $messages = [5 => $message];

        $stream = new ArrayStream($messages);

        self::assertSame(5, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertSame($message, $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(5, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertSame(null, $stream->current());
        self::assertSame(true, $stream->end());
    }

    public function testClose(): void
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
        self::assertSame(false, $stream->end());

        $stream->close();

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertSame($message, $stream->current());
        self::assertSame(false, $stream->end());
    }
}
