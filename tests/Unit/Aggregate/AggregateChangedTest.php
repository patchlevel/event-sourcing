<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateException;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

use function date;

class AggregateChangedTest extends TestCase
{
    public function testCreateEvent(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $event = ProfileCreated::raise($id, $email);

        self::assertEquals($id, $event->profileId());
        self::assertEquals($email, $event->email());
        self::assertEquals($id->toString(), $event->aggregateId());
        self::assertEquals(null, $event->playhead());
        self::assertEquals(null, $event->recordedOn());
        self::assertEquals(
            [
                'profileId' => '1',
                'email' => 'd.a.badura@gmail.com',
            ],
            $event->payload()
        );
    }

    public function testRecordNow(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $event = ProfileCreated::raise($id, $email);
        $recordedEvent = $event->recordNow(0);

        self::assertEquals($id, $recordedEvent->profileId());
        self::assertEquals($email, $recordedEvent->email());
        self::assertEquals(0, $recordedEvent->playhead());
        self::assertInstanceOf(DateTimeImmutable::class, $recordedEvent->recordedOn());
        self::assertEquals(
            [
                'profileId' => '1',
                'email' => 'd.a.badura@gmail.com',
            ],
            $recordedEvent->payload()
        );
    }

    public function testSerialize(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $event = ProfileCreated::raise($id, $email);

        $beforeRecording = new DateTimeImmutable();
        $recordedEvent = $event->recordNow(0);
        $afterRecording = new DateTimeImmutable();

        $serializedEvent = $recordedEvent->serialize();

        self::assertCount(5, $serializedEvent);

        self::assertArrayHasKey('aggregateId', $serializedEvent);
        self::assertEquals('1', $serializedEvent['aggregateId']);

        self::assertArrayHasKey('playhead', $serializedEvent);
        self::assertEquals(0, $serializedEvent['playhead']);

        self::assertArrayHasKey('event', $serializedEvent);
        self::assertEquals(
            'Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated',
            $serializedEvent['event']
        );

        self::assertArrayHasKey('payload', $serializedEvent);
        self::assertEquals('{"profileId":"1","email":"d.a.badura@gmail.com"}', $serializedEvent['payload']);

        self::assertArrayHasKey('recordedOn', $serializedEvent);
        self::assertDateTimeImmutableBetween(
            $beforeRecording,
            $afterRecording,
            $serializedEvent['recordedOn'],
        );
    }

    public function testDeserialize(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $event = ProfileCreated::deserialize([
            'aggregateId' => '1',
            'playhead' => 0,
            'event' => 'Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated',
            'payload' => '{"profileId":"1","email":"d.a.badura@gmail.com"}',
            'recordedOn' => new DateTimeImmutable('2020-11-20 13:57:49'),
        ]);

        self::assertEquals($id, $event->profileId());
        self::assertEquals($email, $event->email());
        self::assertEquals(0, $event->playhead());
        self::assertInstanceOf(DateTimeImmutable::class, $event->recordedOn());
        self::assertEquals(
            [
                'profileId' => '1',
                'email' => 'd.a.badura@gmail.com',
            ],
            $event->payload()
        );
    }

    public function testDeserializeClassNotFound(): void
    {
        $this->expectException(AggregateException::class);

        ProfileCreated::deserialize([
            'aggregateId' => '1',
            'playhead' => 0,
            'event' => 'Patchlevel\EventSourcing\Tests\Unit\Fixture\NotFound',
            'payload' => '{"profileId":"1","email":"d.a.badura@gmail.com"}',
            'recordedOn' => '2020-11-20 13:57:49',
        ]);
    }

    public function testDeserializeAndSerialize(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $event = ProfileCreated::raise($id, $email);
        $recordedEvent = $event->recordNow(0);

        $serializedEvent = $recordedEvent->serialize();
        $event = ProfileCreated::deserialize($serializedEvent);

        self::assertEquals($id, $event->profileId());
        self::assertEquals($email, $event->email());
        self::assertEquals(0, $event->playhead());
        self::assertInstanceOf(DateTimeImmutable::class, $event->recordedOn());
        self::assertEquals(
            [
                'profileId' => '1',
                'email' => 'd.a.badura@gmail.com',
            ],
            $event->payload()
        );
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
