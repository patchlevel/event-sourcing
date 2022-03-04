<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use DateTimeImmutable;
use Error;
use Patchlevel\EventSourcing\Aggregate\AggregateChangeNotRecorded;
use Patchlevel\EventSourcing\Aggregate\AggregateChangeRecordedAlready;
use Patchlevel\EventSourcing\Clock;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreatedWithCustomRecordedOn;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisitedWithClock;
use PHPUnit\Framework\TestCase;

use const PHP_VERSION_ID;

/** @covers \Patchlevel\EventSourcing\Aggregate\AggregateChanged */
class AggregateChangedTest extends TestCase
{
    public function testCreateEvent(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise($id, $email);

        self::assertEquals($id, $event->profileId());
        self::assertEquals($email, $event->email());
        self::assertSame($id->toString(), $event->aggregateId());
        self::assertNull($event->playhead());
        self::assertNull($event->recordedOn());
        self::assertSame(
            [
                'profileId' => '1',
                'email' => 'hallo@patchlevel.de',
            ],
            $event->payload()
        );
    }

    public function testRecordNow(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise($id, $email);
        $recordedEvent = $event->recordNow(1);

        self::assertInstanceOf(ProfileCreated::class, $recordedEvent);
        self::assertEquals($id, $recordedEvent->profileId());
        self::assertEquals($email, $recordedEvent->email());
        self::assertSame(1, $recordedEvent->playhead());
        self::assertInstanceOf(DateTimeImmutable::class, $recordedEvent->recordedOn());
        self::assertSame(
            [
                'profileId' => '1',
                'email' => 'hallo@patchlevel.de',
            ],
            $recordedEvent->payload()
        );
    }

    public function testEventAlreadyBeenRecorded(): void
    {
        $this->expectException(AggregateChangeRecordedAlready::class);

        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise($id, $email);
        $recordedEvent = $event->recordNow(1);

        $recordedEvent->recordNow(1);
    }

    public function testSerialize(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise($id, $email);

        $beforeRecording = new DateTimeImmutable();
        $recordedEvent = $event->recordNow(1);
        $afterRecording = new DateTimeImmutable();

        $serializedEvent = $recordedEvent->serialize();

        self::assertCount(5, $serializedEvent);

        self::assertArrayHasKey('aggregate_id', $serializedEvent);
        self::assertSame('1', $serializedEvent['aggregate_id']);

        self::assertArrayHasKey('playhead', $serializedEvent);
        self::assertSame(1, $serializedEvent['playhead']);

        self::assertArrayHasKey('event', $serializedEvent);
        self::assertSame(
            'Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated',
            $serializedEvent['event']
        );

        self::assertArrayHasKey('payload', $serializedEvent);
        self::assertSame('{"profileId":"1","email":"hallo@patchlevel.de"}', $serializedEvent['payload']);

        self::assertArrayHasKey('recorded_on', $serializedEvent);
        self::assertDateTimeImmutableBetween(
            $beforeRecording,
            $afterRecording,
            $serializedEvent['recorded_on'],
        );
    }

    public function testSerializeNotRecorded(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise($id, $email);

        $this->expectException(AggregateChangeNotRecorded::class);
        $event->serialize();
    }

    public function testDeserialize(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::deserialize([
            'aggregate_id' => '1',
            'playhead' => 0,
            'event' => 'Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated',
            'payload' => '{"profileId":"1","email":"hallo@patchlevel.de"}',
            'recorded_on' => new DateTimeImmutable('2020-11-20 13:57:49'),
        ]);

        self::assertInstanceOf(ProfileCreated::class, $event);
        self::assertEquals($id, $event->profileId());
        self::assertEquals($email, $event->email());
        self::assertSame(0, $event->playhead());
        self::assertInstanceOf(DateTimeImmutable::class, $event->recordedOn());
        self::assertSame(
            [
                'profileId' => '1',
                'email' => 'hallo@patchlevel.de',
            ],
            $event->payload()
        );
    }

    public function testDeserializeClassNotFound(): void
    {
        $this->expectException(Error::class);

        if (PHP_VERSION_ID >= 80000) {
            $this->expectExceptionMessage('Class "Patchlevel\EventSourcing\Tests\Unit\Fixture\NotFound" not found');
        } else {
            $this->expectExceptionMessage('Class \'Patchlevel\EventSourcing\Tests\Unit\Fixture\NotFound\' not found');
        }

        ProfileCreated::deserialize([
            'aggregate_id' => '1',
            'playhead' => 0,
            'event' => 'Patchlevel\EventSourcing\Tests\Unit\Fixture\NotFound',
            'payload' => '{"profileId":"1","email":"hallo@patchlevel.de"}',
            'recorded_on' => '2020-11-20 13:57:49',
        ]);
    }

    public function testDeserializeAndSerialize(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise($id, $email);
        $recordedEvent = $event->recordNow(1);

        $serializedEvent = $recordedEvent->serialize();
        $event = ProfileCreated::deserialize($serializedEvent);

        self::assertInstanceOf(ProfileCreated::class, $event);
        self::assertEquals($id, $event->profileId());
        self::assertEquals($email, $event->email());
        self::assertSame(1, $event->playhead());
        self::assertInstanceOf(DateTimeImmutable::class, $event->recordedOn());
        self::assertSame(
            [
                'profileId' => '1',
                'email' => 'hallo@patchlevel.de',
            ],
            $event->payload()
        );
    }

    public function testCustomRecordedOn(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreatedWithCustomRecordedOn::raise($id, $email);
        $recordedEvent = $event->recordNow(1);

        self::assertEquals(new DateTimeImmutable('1.1.2022 10:00:00'), $recordedEvent->recordedOn());
    }

    public function testRecordedAtWithFreezedClock(): void
    {
        $date = new DateTimeImmutable();

        Clock::freeze($date);

        $profile1 = ProfileId::fromString('1');
        $profile2 = ProfileId::fromString('2');

        $event = ProfileVisited::raise($profile1, $profile2);
        $recordedEvent = $event->recordNow(1);

        self::assertSame($date, $recordedEvent->recordedOn());
    }

    private static function assertDateTimeImmutableBetween(
        DateTimeImmutable $fromExpected,
        DateTimeImmutable $toExpected,
        DateTimeImmutable $actual
    ): void {
        self::assertGreaterThanOrEqual($fromExpected, $actual);
        self::assertLessThanOrEqual($toExpected, $actual);
    }
}
