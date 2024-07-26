<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use ArrayIterator;
use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\StreamClosed;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStoreStream;
use Patchlevel\EventSourcing\Store\StreamHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Throwable;

use function iterator_to_array;

/** @covers \Patchlevel\EventSourcing\Store\StreamDoctrineDbalStoreStream */
final class StreamDoctrineDbalStreamTest extends TestCase
{
    use ProphecyTrait;

    public function testEmpty(): void
    {
        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->shouldBeCalledOnce()->willReturn(new ArrayIterator());

        $stream = new StreamDoctrineDbalStoreStream(
            $result->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            $platform->reveal(),
        );

        self::assertSame(null, $stream->position());
        self::assertSame(null, $stream->current());
        self::assertSame(null, $stream->index());
        self::assertSame(true, $stream->end());

        $this->expectException(Throwable::class);
        iterator_to_array($stream);
    }

    public function testOneMessage(): void
    {
        $messages = [
            [
                'id' => 1,
                'event' => 'profile_created',
                'payload' => '{}',
                'stream' => 'profile-1',
                'playhead' => 1,
                'recorded_on' => '2022-10-10 10:10:10',
                'archived' => '0',
                'new_stream_start' => '0',
                'custom_headers' => '{}',
            ],
        ];

        $event = new ProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );
        $message = Message::create($event)
            ->withHeader(new StreamHeader('profile-1', 1, new DateTimeImmutable('2022-10-10 10:10:10')));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->deserialize(new SerializedEvent('profile_created', '{}'))
            ->shouldBeCalledOnce()
            ->willReturn($event);

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->deserialize('{}')->shouldBeCalledOnce()->willReturn([]);

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getDateTimeTzFormatString()->shouldBeCalledOnce()->willReturn('Y-m-d H:i:s');

        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->shouldBeCalledOnce()->willReturn(new ArrayIterator($messages));

        $stream = new StreamDoctrineDbalStoreStream(
            $result->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            $platform->reveal(),
        );

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertEquals($message, $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertSame(null, $stream->current());
        self::assertSame(true, $stream->end());
    }

    public function testMultipleMessages(): void
    {
        $messagesArray = [
            [
                'id' => 1,
                'event' => 'profile_created',
                'payload' => '{}',
                'stream' => 'profile-1',
                'playhead' => 1,
                'recorded_on' => '2022-10-10 10:10:10',
                'archived' => '0',
                'new_stream_start' => '0',
                'custom_headers' => '{}',
            ],
            [
                'id' => 2,
                'event' => 'profile_created2',
                'payload' => '{}',
                'stream' => 'profile-2',
                'playhead' => null,
                'recorded_on' => '2022-10-10 10:10:10',
                'archived' => '0',
                'new_stream_start' => '0',
                'custom_headers' => '{}',
            ],
            [
                'id' => 3,
                'event' => 'profile_created3',
                'payload' => '{}',
                'stream' => 'profile-3',
                'playhead' => 1,
                'recorded_on' => '2022-10-10 10:10:10',
                'archived' => '0',
                'new_stream_start' => '0',
                'custom_headers' => '{}',
            ],
        ];

        $event = new ProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );

        $messages = [
            Message::create($event)
                ->withHeader(new StreamHeader('profile-1', 1, new DateTimeImmutable('2022-10-10 10:10:10'))),
            Message::create($event)
                ->withHeader(new StreamHeader('profile-2', null, new DateTimeImmutable('2022-10-10 10:10:10'))),
            Message::create($event)
                ->withHeader(new StreamHeader('profile-3', 1, new DateTimeImmutable('2022-10-10 10:10:10'))),
        ];

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->deserialize(new SerializedEvent('profile_created', '{}'))
            ->shouldBeCalledOnce()
            ->willReturn($event);
        $eventSerializer->deserialize(new SerializedEvent('profile_created2', '{}'))
            ->shouldBeCalledOnce()
            ->willReturn($event);
        $eventSerializer->deserialize(new SerializedEvent('profile_created3', '{}'))
            ->shouldBeCalledOnce()
            ->willReturn($event);

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->deserialize('{}')->shouldBeCalledTimes(3)->willReturn([]);

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getDateTimeTzFormatString()->shouldBeCalledTimes(3)->willReturn('Y-m-d H:i:s');

        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->shouldBeCalledOnce()->willReturn(new ArrayIterator($messagesArray));

        $stream = new StreamDoctrineDbalStoreStream(
            $result->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            $platform->reveal(),
        );

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertEquals($messages[0], $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(2, $stream->index());
        self::assertSame(1, $stream->position());
        self::assertEquals($messages[1], $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(3, $stream->index());
        self::assertSame(2, $stream->position());
        self::assertEquals($messages[2], $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(3, $stream->index());
        self::assertSame(2, $stream->position());
        self::assertSame(null, $stream->current());
        self::assertSame(true, $stream->end());
    }

    public function testWithNoList(): void
    {
        $messages = [
            5 => [
                'id' => 5,
                'event' => 'profile_created',
                'payload' => '{}',
                'stream' => 'profile-1',
                'playhead' => 1,
                'recorded_on' => '2022-10-10 10:10:10',
                'archived' => '0',
                'new_stream_start' => '0',
                'custom_headers' => '{}',
            ],
        ];

        $event = new ProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );
        $message = Message::create($event)
            ->withHeader(new StreamHeader('profile-1', 1, new DateTimeImmutable('2022-10-10 10:10:10')));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->deserialize(new SerializedEvent('profile_created', '{}'))
            ->shouldBeCalledOnce()
            ->willReturn($event);

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->deserialize('{}')->shouldBeCalledOnce()->willReturn([]);

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getDateTimeTzFormatString()->shouldBeCalledOnce()->willReturn('Y-m-d H:i:s');

        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->shouldBeCalledOnce()->willReturn(new ArrayIterator($messages));

        $stream = new StreamDoctrineDbalStoreStream(
            $result->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            $platform->reveal(),
        );

        self::assertSame(5, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertEquals($message, $stream->current());
        self::assertSame(false, $stream->end());

        $stream->next();

        self::assertSame(5, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertSame(null, $stream->current());
        self::assertSame(true, $stream->end());
    }

    public function testClose(): void
    {
        $messages = [
            [
                'id' => 1,
                'event' => 'profile_created',
                'payload' => '{}',
                'stream' => 'profile-1',
                'playhead' => 1,
                'recorded_on' => '2022-10-10 10:10:10',
                'archived' => '0',
                'new_stream_start' => '0',
                'custom_headers' => '{}',
            ],
        ];

        $event = new ProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );
        $message = Message::create($event)
            ->withHeader(new StreamHeader('profile-1', 1, new DateTimeImmutable('2022-10-10 10:10:10')));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->deserialize(new SerializedEvent('profile_created', '{}'))
            ->shouldBeCalledOnce()
            ->willReturn($event);

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->deserialize('{}')->shouldBeCalledOnce()->willReturn([]);

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getDateTimeTzFormatString()->shouldBeCalledOnce()->willReturn('Y-m-d H:i:s');

        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->shouldBeCalledOnce()->willReturn(new ArrayIterator($messages));
        $result->free()->shouldBeCalledOnce();

        $stream = new StreamDoctrineDbalStoreStream(
            $result->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            $platform->reveal(),
        );

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());
        self::assertEquals($message, $stream->current());
        self::assertSame(false, $stream->end());

        $stream->close();

        $this->expectException(StreamClosed::class);
        $stream->index();
    }

    public function testPositionEmpty(): void
    {
        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->shouldBeCalledOnce()->willReturn(new ArrayIterator());

        $stream = new StreamDoctrineDbalStoreStream(
            $result->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            $platform->reveal(),
        );

        $position = $stream->position();

        self::assertNull($position);
    }

    public function testPosition(): void
    {
        $messages = [
            [
                'id' => 1,
                'event' => 'profile_created',
                'payload' => '{}',
                'stream' => 'profile-1',
                'playhead' => 1,
                'recorded_on' => '2022-10-10 10:10:10',
                'archived' => '0',
                'new_stream_start' => '0',
                'custom_headers' => '{}',
            ],
        ];

        $event = new ProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->deserialize(new SerializedEvent('profile_created', '{}'))
            ->shouldBeCalledOnce()
            ->willReturn($event);

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->deserialize('{}')->shouldBeCalledOnce()->willReturn([]);

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getDateTimeTzFormatString()->shouldBeCalledOnce()->willReturn('Y-m-d H:i:s');

        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->shouldBeCalledOnce()->willReturn(new ArrayIterator($messages));

        $stream = new StreamDoctrineDbalStoreStream(
            $result->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            $platform->reveal(),
        );

        $position = $stream->position();

        self::assertSame(0, $position);
    }
}
