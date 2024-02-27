<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus\Serializer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\DeserializeFailed;
use Patchlevel\EventSourcing\EventBus\Serializer\EventSerializerMessageSerializer;
use Patchlevel\EventSourcing\EventBus\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\EventBus\Serializer\SerializedHeader;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\Hydrator\MetadataHydrator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\EventBus\Serializer\EventSerializerMessageSerializer */
final class EventSerializerMessageSerializerTest extends TestCase
{
    use ProphecyTrait;

    public function testSerialize(): void
    {
        $event = new ProfileVisited(
            ProfileId::fromString('foo'),
        );

        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')))
            ->withHeader(new ArchivedHeader(false));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($event)->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_visited',
            '{id: foo}',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize($message->headers())->shouldBeCalledOnce()->willReturn([
            new SerializedHeader('aggregate', '{aggregateName:profile,aggregateId:1,playhead:1,recordedOn:2020-01-01T20:00:00+01:00}'),
            new SerializedHeader('archived', '{archived:false}'),
        ]);

        $serializer = new EventSerializerMessageSerializer(
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            new JsonEncoder(),
        );

        $content = $serializer->serialize($message);

        self::assertEquals('{"serializedEvent":{"name":"profile_visited","payload":"{id: foo}"},"headers":[{"name":"aggregate","payload":"{aggregateName:profile,aggregateId:1,playhead:1,recordedOn:2020-01-01T20:00:00+01:00}"},{"name":"archived","payload":"{archived:false}"}]}', $content);
    }

    public function testDeserialize(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('foo'));

        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')))
            ->withHeader(new ArchivedHeader(false));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->deserialize(new SerializedEvent(
            'profile_visited',
            '{id: foo}',
        ))->shouldBeCalledOnce()->willReturn($event);

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->deserialize([
            new SerializedHeader('aggregate', '{aggregateName:profile,aggregateId:1,playhead:1,recordedOn:2020-01-01T20:00:00+01:00}'),
            new SerializedHeader('archived', '{archived:false}'),
        ])->shouldBeCalledOnce()->willReturn($message->headers());

        $serializer = new EventSerializerMessageSerializer(
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            new JsonEncoder(),
        );

        $deserializedMessage = $serializer->deserialize('{"serializedEvent":{"name":"profile_visited","payload":"{id: foo}"},"headers":[{"name":"aggregate","payload": "{aggregateName:profile,aggregateId:1,playhead:1,recordedOn:2020-01-01T20:00:00+01:00}"}, {"name": "archived", "payload":"{archived:false}"}]}');

        self::assertEquals($message, $deserializedMessage);
    }

    public function testDeserializeDecodeFailedInvalidData(): void
    {
        $this->expectException(DeserializeFailed::class);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $serializer = new EventSerializerMessageSerializer(
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            new JsonEncoder(),
        );

        $serializer->deserialize('{}');
    }

    public function testEquals(): void
    {
        $event = new ProfileVisited(
            ProfileId::fromString('foo'),
        );

        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($event)->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_visited',
            '{id: foo}',
        ));
        $eventSerializer->deserialize(new SerializedEvent(
            'profile_visited',
            '{id: foo}',
        ))->shouldBeCalledOnce()->willReturn($event);

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize($message->headers())->shouldBeCalledOnce()->willReturn([
            new SerializedHeader('aggregate', '{aggregateName:profile,aggregateId:1,playhead:1,recordedOn:2020-01-01T20:00:00+01:00}'),
        ]);
        $headersSerializer->deserialize([
            new SerializedHeader('aggregate', '{aggregateName:profile,aggregateId:1,playhead:1,recordedOn:2020-01-01T20:00:00+01:00}'),
        ])->shouldBeCalledOnce()->willReturn($message->headers());

        $serializer = new EventSerializerMessageSerializer(
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            new JsonEncoder(),
        );

        $content = $serializer->serialize($message);
        $clonedMessage = $serializer->deserialize($content);

        self::assertEquals($message, $clonedMessage);
    }
}
