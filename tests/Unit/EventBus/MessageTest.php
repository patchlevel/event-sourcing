<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\EventBus\Message */
class MessageTest extends TestCase
{
    public function tearDown(): void
    {
        Clock::reset();
    }

    public function testCreateMessage(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        Clock::freeze($recordedAt);

        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise(
            $id,
            $email
        );

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event
        );

        self::assertSame(Profile::class, $message->aggregateClass());
        self::assertSame('1', $message->aggregateId());
        self::assertSame(1, $message->playhead());
        self::assertEquals($event, $message->event());
        self::assertEquals($recordedAt, $message->recordedOn());
    }

    public function testCreateMessageWithSpecificRecordOn(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise(
            $id,
            $email
        );

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event,
            $recordedAt
        );

        self::assertSame(Profile::class, $message->aggregateClass());
        self::assertSame('1', $message->aggregateId());
        self::assertSame(1, $message->playhead());
        self::assertEquals($event, $message->event());
        self::assertEquals($recordedAt, $message->recordedOn());
    }

    public function testSerialize(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise(
            $id,
            $email
        );

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event,
            $recordedAt
        );

        self::assertSame([
            'aggregate_class' => 'Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile',
            'aggregate_id' => '1',
            'playhead' => 1,
            'event' => 'Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated',
            'payload' => '{"profileId":"1","email":"hallo@patchlevel.de"}',
            'recorded_on' => $recordedAt,
        ], $message->serialize());
    }

    public function testDeserialize(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        $message = Message::deserialize([
            'aggregate_class' => 'Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile',
            'aggregate_id' => '1',
            'playhead' => 1,
            'event' => 'Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated',
            'payload' => '{"profileId":"1","email":"hallo@patchlevel.de"}',
            'recorded_on' => $recordedAt,
        ]);

        self::assertSame(Profile::class, $message->aggregateClass());
        self::assertSame('1', $message->aggregateId());
        self::assertSame(1, $message->playhead());
        self::assertEquals($recordedAt, $message->recordedOn());

        $event = $message->event();

        self::assertInstanceOf(ProfileCreated::class, $event);
        self::assertEquals(ProfileId::fromString('1'), $event->profileId());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $event->email());
    }
}
