<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Aggregate\AggregateException;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

class AggregateChangedTest extends TestCase
{
    public function testCreateEvent()
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $event = ProfileCreated::raise($id, $email);

        self::assertEquals($id, $event->profileId());
        self::assertEquals($email, $event->email());
        self::assertEquals(null, $event->playhead());
        self::assertEquals(null, $event->recordedOn());
        self::assertEquals(
            [
                'profileId' => '1',
                'email' => 'd.a.badura@gmail.com'
            ],
            $event->payload()
        );
    }

    public function testRecordNow()
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $event = ProfileCreated::raise($id, $email);
        $recordedEvent = $event->recordNow(0);

        self::assertEquals($id, $recordedEvent->profileId());
        self::assertEquals($email, $recordedEvent->email());
        self::assertEquals(0, $recordedEvent->playhead());
        self::assertInstanceOf(\DateTimeImmutable::class, $recordedEvent->recordedOn());
        self::assertEquals(
            [
                'profileId' => '1',
                'email' => 'd.a.badura@gmail.com'
            ],
            $recordedEvent->payload()
        );
    }

    public function testSerialize()
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $event = ProfileCreated::raise($id, $email);
        $recordedEvent = $event->recordNow(0);

        self::assertEquals(
            [
                'aggregateId' => '1',
                'playhead' => 0,
                'event' => 'Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\ProfileCreated',
                'payload' => '{"profileId":"1","email":"d.a.badura@gmail.com"}',
                'recordedOn' => '2020-11-20 13:57:49',
            ],
            $recordedEvent->serialize()
        );
    }

    public function testDeserialize()
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $event = ProfileCreated::deserialize([
            'aggregateId' => '1',
            'playhead' => 0,
            'event' => 'Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\ProfileCreated',
            'payload' => '{"profileId":"1","email":"d.a.badura@gmail.com"}',
            'recordedOn' => '2020-11-20 13:57:49',
        ]);

        self::assertEquals($id, $event->profileId());
        self::assertEquals($email, $event->email());
        self::assertEquals(0, $event->playhead());
        self::assertInstanceOf(\DateTimeImmutable::class, $event->recordedOn());
        self::assertEquals(
            [
                'profileId' => '1',
                'email' => 'd.a.badura@gmail.com'
            ],
            $event->payload()
        );
    }

    public function testDeserializeClassNotFound()
    {
        $this->expectException(AggregateException::class);

        ProfileCreated::deserialize([
            'aggregateId' => '1',
            'playhead' => 0,
            'event' => 'Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\NotFound',
            'payload' => '{"profileId":"1","email":"d.a.badura@gmail.com"}',
            'recordedOn' => '2020-11-20 13:57:49',
        ]);
    }


    public function testDeserializeAndSerialize()
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
        self::assertInstanceOf(\DateTimeImmutable::class, $event->recordedOn());
        self::assertEquals(
            [
                'profileId' => '1',
                'email' => 'd.a.badura@gmail.com'
            ],
            $event->payload()
        );
    }
}
