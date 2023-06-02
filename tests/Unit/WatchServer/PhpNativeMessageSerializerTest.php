<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\WatchServer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\WatchServer\PhpNativeMessageSerializer;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\WatchServer\PhpNativeMessageSerializer */
final class PhpNativeMessageSerializerTest extends TestCase
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
        $eventSerializer
            ->serialize($event)
            ->willReturn(new SerializedEvent('profile.visited', '{"profileId": "foo"}'))
            ->shouldBeCalledOnce();

        $nativeSerializer = new PhpNativeMessageSerializer($eventSerializer->reveal());

        $content = $nativeSerializer->serialize($message);

        self::assertEquals('YTozOntzOjU6ImV2ZW50IjtzOjE1OiJwcm9maWxlLnZpc2l0ZWQiO3M6NzoicGF5bG9hZCI7czoyMDoieyJwcm9maWxlSWQiOiAiZm9vIn0iO3M6NzoiaGVhZGVycyI7YTozOntzOjEwOiJyZWNvcmRlZE9uIjtPOjE3OiJEYXRlVGltZUltbXV0YWJsZSI6Mzp7czo0OiJkYXRlIjtzOjI2OiIyMDIwLTAxLTAxIDIwOjAwOjAwLjAwMDAwMCI7czoxMzoidGltZXpvbmVfdHlwZSI7aToxO3M6ODoidGltZXpvbmUiO3M6NjoiKzAxOjAwIjt9czoxNDoibmV3U3RyZWFtU3RhcnQiO2I6MDtzOjg6ImFyY2hpdmVkIjtiOjA7fX0=', $content);
    }

    public function testDeserialize(): void
    {
        $event = new ProfileVisited(
            ProfileId::fromString('foo'),
        );

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer
            ->deserialize(new SerializedEvent('profile.visited', '{"profileId": "foo"}'))
            ->willReturn($event)
            ->shouldBeCalledOnce();

        $nativeSerializer = new PhpNativeMessageSerializer($eventSerializer->reveal());

        $message = $nativeSerializer->deserialize('YTozOntzOjU6ImV2ZW50IjtzOjE1OiJwcm9maWxlLnZpc2l0ZWQiO3M6NzoicGF5bG9hZCI7czoyMDoieyJwcm9maWxlSWQiOiAiZm9vIn0iO3M6NzoiaGVhZGVycyI7YTozOntzOjEwOiJyZWNvcmRlZE9uIjtPOjE3OiJEYXRlVGltZUltbXV0YWJsZSI6Mzp7czo0OiJkYXRlIjtzOjI2OiIyMDIwLTAxLTAxIDIwOjAwOjAwLjAwMDAwMCI7czoxMzoidGltZXpvbmVfdHlwZSI7aToxO3M6ODoidGltZXpvbmUiO3M6NjoiKzAxOjAwIjt9czoxNDoibmV3U3RyZWFtU3RhcnQiO2I6MDtzOjg6ImFyY2hpdmVkIjtiOjA7fX0=');

        self::assertEquals($event, $message->event());
        self::assertEquals([
            'recordedOn' => new DateTimeImmutable('2020-01-01T20:00:00.000000+0100'),
            'newStreamStart' => false,
            'archived' => false,
        ], $message->headers());
    }

    public function testEquals(): void
    {
        $event = new ProfileVisited(
            ProfileId::fromString('foo'),
        );

        $message = Message::create($event)
            ->withRecordedOn(new DateTimeImmutable('2020-01-01T20:00:00.000000+0100'));

        $eventSerializer = $this->prophesize(EventSerializer::class);

        $eventSerializer
            ->serialize($event)
            ->willReturn(new SerializedEvent('profile.visited', '{"profileId": "foo"}'))
            ->shouldBeCalledOnce();

        $eventSerializer
            ->deserialize(new SerializedEvent('profile.visited', '{"profileId": "foo"}'))
            ->willReturn($event)
            ->shouldBeCalledOnce();

        $nativeSerializer = new PhpNativeMessageSerializer($eventSerializer->reveal());

        $content = $nativeSerializer->serialize($message);
        $clonedMessage = $nativeSerializer->deserialize($content);

        self::assertEquals($message, $clonedMessage);
    }
}
