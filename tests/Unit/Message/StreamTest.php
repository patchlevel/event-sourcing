<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Message\StreamClosed;
use Patchlevel\EventSourcing\Message\StreamNotRewindable;
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator;
use Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stream::class)]
final class StreamTest extends TestCase
{
    public function testEmpty(): void
    {
        $stream = new Stream();

        self::assertSame(null, $stream->position());
        self::assertSame(null, $stream->current());
        self::assertSame(null, $stream->index());
        self::assertSame(true, $stream->end());
    }

    public function testOneMessageInList(): void
    {
        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('foo'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $stream = new Stream([$message]);

        self::assertSame(0, $stream->position());
        self::assertSame(0, $stream->index());
        self::assertSame($message, $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(0, $stream->position());
        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->current());
        self::assertSame(true, $stream->end());
    }

    public function testWithOffsetKey(): void
    {
        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('foo'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $stream = new Stream([5 => $message]);

        self::assertSame(0, $stream->position());
        self::assertSame(5, $stream->index());
        self::assertSame($message, $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(0, $stream->position());
        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->current());
        self::assertSame(true, $stream->end());
    }

    public function testClose(): void
    {
        $this->expectException(StreamClosed::class);

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('foo'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $stream = new Stream([$message]);

        $stream->close();
        $stream->index();
    }

    public function testWithOneTranslator(): void
    {
        $messages = $this->messages();

        $stream = new Stream($messages);
        $stream = $stream->transform(
            new ExcludeEventTranslator([ProfileCreated::class]),
        );

        $resultMessages = $stream->toList();

        self::assertCount(3, $resultMessages);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[0]->event());
        self::assertSame('profile-1', $resultMessages[0]->header(StreamNameHeader::class)->streamName);
        self::assertSame(2, $resultMessages[0]->header(PlayheadHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[1]->event());
        self::assertSame('profile-1', $resultMessages[1]->header(StreamNameHeader::class)->streamName);
        self::assertSame(3, $resultMessages[1]->header(PlayheadHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[2]->event());
        self::assertSame('profile-2', $resultMessages[2]->header(StreamNameHeader::class)->streamName);
        self::assertSame(2, $resultMessages[2]->header(PlayheadHeader::class)->playhead);
    }

    public function testWithMiddlewares(): void
    {
        $messages = $this->messages();

        $stream = new Stream($messages);
        $stream = $stream->transform(
            new ExcludeEventTranslator([ProfileCreated::class]),
            new RecalculatePlayheadTranslator(),
        );

        $resultMessages = $stream->toList();

        self::assertCount(3, $resultMessages);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[0]->event());
        self::assertSame('profile-1', $resultMessages[0]->header(StreamNameHeader::class)->streamName);
        self::assertSame(1, $resultMessages[0]->header(PlayheadHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[1]->event());
        self::assertSame('profile-1', $resultMessages[1]->header(StreamNameHeader::class)->streamName);
        self::assertSame(2, $resultMessages[1]->header(PlayheadHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[2]->event());
        self::assertSame('profile-2', $resultMessages[2]->header(StreamNameHeader::class)->streamName);
        self::assertSame(1, $resultMessages[2]->header(PlayheadHeader::class)->playhead);
    }

    public function testTransformIsSinglePass(): void
    {
        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('foo'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $stream = new Stream([$message]);
        $transformedStream = $stream->transform();

        self::assertCount(1, $transformedStream->toList());

        $this->expectException(StreamNotRewindable::class);
        $transformedStream->toList();
    }

    /** @return list<Message> */
    private function messages(): array
    {
        return [
            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hallo@patchlevel.de'),
                ),
            )
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(3))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),

            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('2'),
                    Email::fromString('hallo@patchlevel.de'),
                ),
            )
                ->withHeader(new StreamNameHeader('profile-2'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),

            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('2'),
                ),
            )
                ->withHeader(new StreamNameHeader('profile-2'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
        ];
    }
}
