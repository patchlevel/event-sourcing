<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus\Serializer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\DeserializeFailed;
use Patchlevel\EventSourcing\EventBus\Serializer\EventSerializerMessageSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
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
            ->withRecordedOn(new DateTimeImmutable('2020-01-01T20:00:00.000000+0100'));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($event)->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_visited',
            '{id: foo}',
        ));

        $serializer = new EventSerializerMessageSerializer(
            $eventSerializer->reveal(),
        );

        $content = $serializer->serialize($message);

        self::assertEquals('YToyOntzOjE1OiJzZXJpYWxpemVkRXZlbnQiO086NTE6IlBhdGNobGV2ZWxcRXZlbnRTb3VyY2luZ1xTZXJpYWxpemVyXFNlcmlhbGl6ZWRFdmVudCI6Mjp7czo0OiJuYW1lIjtzOjE1OiJwcm9maWxlX3Zpc2l0ZWQiO3M6NzoicGF5bG9hZCI7czo5OiJ7aWQ6IGZvb30iO31zOjc6ImhlYWRlcnMiO2E6MTp7czoxMDoicmVjb3JkZWRPbiI7TzoxNzoiRGF0ZVRpbWVJbW11dGFibGUiOjM6e3M6NDoiZGF0ZSI7czoyNjoiMjAyMC0wMS0wMSAyMDowMDowMC4wMDAwMDAiO3M6MTM6InRpbWV6b25lX3R5cGUiO2k6MTtzOjg6InRpbWV6b25lIjtzOjY6IiswMTowMCI7fX19', $content);
    }

    public function testDeserialize(): void
    {
        $event = new ProfileVisited(
            ProfileId::fromString('foo'),
        );

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->deserialize(new SerializedEvent(
            'profile_visited',
            '{id: foo}',
        ))->shouldBeCalledOnce()->willReturn($event);

        $serializer = new EventSerializerMessageSerializer(
            $eventSerializer->reveal(),
        );

        $message = $serializer->deserialize('YToyOntzOjE1OiJzZXJpYWxpemVkRXZlbnQiO086NTE6IlBhdGNobGV2ZWxcRXZlbnRTb3VyY2luZ1xTZXJpYWxpemVyXFNlcmlhbGl6ZWRFdmVudCI6Mjp7czo0OiJuYW1lIjtzOjE1OiJwcm9maWxlX3Zpc2l0ZWQiO3M6NzoicGF5bG9hZCI7czo5OiJ7aWQ6IGZvb30iO31zOjc6ImhlYWRlcnMiO2E6Mzp7czoxMDoicmVjb3JkZWRPbiI7TzoxNzoiRGF0ZVRpbWVJbW11dGFibGUiOjM6e3M6NDoiZGF0ZSI7czoyNjoiMjAyMC0wMS0wMSAyMDowMDowMC4wMDAwMDAiO3M6MTM6InRpbWV6b25lX3R5cGUiO2k6MTtzOjg6InRpbWV6b25lIjtzOjY6IiswMTowMCI7fXM6MTQ6Im5ld1N0cmVhbVN0YXJ0IjtiOjA7czo4OiJhcmNoaXZlZCI7YjowO319');

        self::assertEquals($event, $message->event());
        self::assertEquals([
            'recordedOn' => new DateTimeImmutable('2020-01-01T20:00:00.000000+0100'),
            'newStreamStart' => false,
            'archived' => false,
        ], $message->headers());
    }

    public function testDeserializeDecodeFailed(): void
    {
        $this->expectException(DeserializeFailed::class);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $serializer = new EventSerializerMessageSerializer(
            $eventSerializer->reveal(),
        );

        $serializer->deserialize('!@#%$^&*()');
    }

    public function testDeserializeDecodeFailedInvalidData(): void
    {
        $this->expectException(DeserializeFailed::class);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $serializer = new EventSerializerMessageSerializer(
            $eventSerializer->reveal(),
        );

        $serializer->deserialize('');
    }

    public function testEquals(): void
    {
        $event = new ProfileVisited(
            ProfileId::fromString('foo'),
        );

        $message = Message::create($event)
            ->withRecordedOn(new DateTimeImmutable('2020-01-01T20:00:00.000000+0100'));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($event)->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_visited',
            '{id: foo}',
        ));
        $eventSerializer->deserialize(new SerializedEvent(
            'profile_visited',
            '{id: foo}',
        ))->shouldBeCalledOnce()->willReturn($event);

        $serializer = new EventSerializerMessageSerializer(
            $eventSerializer->reveal(),
        );

        $content = $serializer->serialize($message);
        $clonedMessage = $serializer->deserialize($content);

        self::assertEquals($message, $clonedMessage);
    }
}
