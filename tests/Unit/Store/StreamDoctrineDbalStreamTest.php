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
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\StreamClosed;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStoreStream;
use Patchlevel\EventSourcing\Tests\ReturnCallback;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;

use function iterator_to_array;

#[CoversClass(StreamDoctrineDbalStoreStream::class)]
final class StreamDoctrineDbalStreamTest extends TestCase
{
    public function testEmpty(): void
    {
        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $platform = $this->createMock(AbstractPlatform::class);

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('iterateAssociative')->willReturn(new ArrayIterator());

        $stream = new StreamDoctrineDbalStoreStream(
            $result,
            $eventSerializer,
            $headersSerializer,
            $platform,
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
                'event_id' => '1',
                'event_name' => 'profile_created',
                'event_payload' => '{}',
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
            ->withHeader(new IndexHeader(1))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2022-10-10 10:10:10')));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->expects($this->once())->method('deserialize')->with(new SerializedEvent('profile_created', '{}'))
            ->willReturn($event);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->expects($this->once())->method('deserialize')->with('{}')->willReturn([]);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->once())->method('getDateTimeTzFormatString')->willReturn('Y-m-d H:i:s');

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('iterateAssociative')->willReturn(new ArrayIterator($messages));

        $stream = new StreamDoctrineDbalStoreStream(
            $result,
            $eventSerializer,
            $headersSerializer,
            $platform,
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
                'event_id' => '1',
                'event_name' => 'profile_created',
                'event_payload' => '{}',
                'stream' => 'profile-1',
                'playhead' => 1,
                'recorded_on' => '2022-10-10 10:10:10',
                'archived' => '0',
                'new_stream_start' => '0',
                'custom_headers' => '{}',
            ],
            [
                'id' => 2,
                'event_id' => '2',
                'event_name' => 'profile_created2',
                'event_payload' => '{}',
                'stream' => 'profile-2',
                'playhead' => null,
                'recorded_on' => '2022-10-10 10:10:10',
                'archived' => '0',
                'new_stream_start' => '0',
                'custom_headers' => '{}',
            ],
            [
                'id' => 3,
                'event_id' => '3',
                'event_name' => 'profile_created3',
                'event_payload' => '{}',
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
                ->withHeader(new IndexHeader(1))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new EventIdHeader('1'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2022-10-10 10:10:10'))),
            Message::create($event)
                ->withHeader(new IndexHeader(2))
                ->withHeader(new StreamNameHeader('profile-2'))
                ->withHeader(new EventIdHeader('2'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2022-10-10 10:10:10'))),
            Message::create($event)
                ->withHeader(new IndexHeader(3))
                ->withHeader(new StreamNameHeader('profile-3'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new EventIdHeader('3'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2022-10-10 10:10:10'))),
        ];

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->exactly(3))
            ->method('deserialize')
            ->willReturnCallback(new ReturnCallback([
                [
                    [new SerializedEvent('profile_created', '{}'), []],
                    $event,
                ],
                [
                    [new SerializedEvent('profile_created2', '{}'), []],
                    $event,
                ],
                [
                    [new SerializedEvent('profile_created3', '{}'), []],
                    $event,
                ],
            ]));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->expects($this->exactly(3))->method('deserialize')->with('{}')->willReturn([]);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->exactly(3))->method('getDateTimeTzFormatString')->willReturn('Y-m-d H:i:s');

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('iterateAssociative')->willReturn(new ArrayIterator($messagesArray));

        $stream = new StreamDoctrineDbalStoreStream(
            $result,
            $eventSerializer,
            $headersSerializer,
            $platform,
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
                'event_id' => '1',
                'event_name' => 'profile_created',
                'event_payload' => '{}',
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
            ->withHeader(new IndexHeader(5))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2022-10-10 10:10:10')));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->expects($this->once())->method('deserialize')->with(new SerializedEvent('profile_created', '{}'))
            ->willReturn($event);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->expects($this->once())->method('deserialize')->with('{}')->willReturn([]);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->once())->method('getDateTimeTzFormatString')->willReturn('Y-m-d H:i:s');

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('iterateAssociative')->willReturn(new ArrayIterator($messages));

        $stream = new StreamDoctrineDbalStoreStream(
            $result,
            $eventSerializer,
            $headersSerializer,
            $platform,
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
                'event_id' => '1',
                'event_name' => 'profile_created',
                'event_payload' => '{}',
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
            ->withHeader(new IndexHeader(1))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2022-10-10 10:10:10')));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->expects($this->once())->method('deserialize')->with(new SerializedEvent('profile_created', '{}'))
            ->willReturn($event);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->expects($this->once())->method('deserialize')->with('{}')->willReturn([]);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->once())->method('getDateTimeTzFormatString')->willReturn('Y-m-d H:i:s');

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('iterateAssociative')->willReturn(new ArrayIterator($messages));
        $result->expects($this->once())->method('free');

        $stream = new StreamDoctrineDbalStoreStream(
            $result,
            $eventSerializer,
            $headersSerializer,
            $platform,
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
        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $platform = $this->createMock(AbstractPlatform::class);

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('iterateAssociative')->willReturn(new ArrayIterator());

        $stream = new StreamDoctrineDbalStoreStream(
            $result,
            $eventSerializer,
            $headersSerializer,
            $platform,
        );

        $position = $stream->position();

        self::assertNull($position);
    }

    public function testPosition(): void
    {
        $messages = [
            [
                'id' => 1,
                'event_id' => '1',
                'event_name' => 'profile_created',
                'event_payload' => '{}',
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

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->expects($this->once())->method('deserialize')->with(new SerializedEvent('profile_created', '{}'))
            ->willReturn($event);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->expects($this->once())->method('deserialize')->with('{}')->willReturn([]);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->once())->method('getDateTimeTzFormatString')->willReturn('Y-m-d H:i:s');

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('iterateAssociative')->willReturn(new ArrayIterator($messages));

        $stream = new StreamDoctrineDbalStoreStream(
            $result,
            $eventSerializer,
            $headersSerializer,
            $platform,
        );

        $position = $stream->position();

        self::assertSame(0, $position);
    }
}
