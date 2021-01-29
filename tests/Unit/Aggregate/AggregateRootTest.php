<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessageId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessagePublished;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

class AggregateRootTest extends TestCase
{
    public function testCreateAggregate(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $profile = Profile::createProfile($id, $email);

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(0, $profile->playhead());
        self::assertEquals($id, $profile->id());
        self::assertEquals($email, $profile->email());

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertEquals(0, $event->playhead());
    }

    public function testExecuteMethod(): void
    {
        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $messageId = MessageId::fromString('2');

        $profile = Profile::createProfile($profileId, $email);

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        self::assertEquals(0, $profile->playhead());
        $event = $events[0];
        self::assertEquals(0, $event->playhead());

        $profile->publishMessage(
            Message::create(
                $messageId,
                'foo'
            )
        );

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(1, $profile->playhead());
        self::assertEquals($profileId, $profile->id());
        self::assertEquals($email, $profile->email());

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertEquals(1, $event->playhead());
    }

    public function testEventWithoutApplyMethod(): void
    {
        $visitorProfile = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('visitor@test.com')
        );

        $events = $visitorProfile->releaseEvents();
        self::assertCount(1, $events);
        self::assertEquals(0, $visitorProfile->playhead());
        $event = $events[0];
        self::assertEquals(0, $event->playhead());

        $visitedProfile = Profile::createProfile(
            ProfileId::fromString('2'),
            Email::fromString('visited@test.com')
        );

        $events = $visitedProfile->releaseEvents();
        self::assertCount(1, $events);
        self::assertEquals(0, $visitedProfile->playhead());
        $event = $events[0];
        self::assertEquals(0, $event->playhead());

        $visitorProfile->visitProfile($visitedProfile->id());

        $events = $visitedProfile->releaseEvents();
        self::assertCount(0, $events);
        self::assertEquals(0, $visitedProfile->playhead());
    }

    public function testInitliazingState(): void
    {
        $eventStream = [
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('profile@test.com')
            ),
            MessagePublished::raise(
                ProfileId::fromString('1'),
                Message::create(
                    MessageId::fromString('2'),
                    'message value'
                )
            ),
        ];

        $profile = Profile::createFromEventStream($eventStream);

        self::assertEquals('1', $profile->id()->toString());
        self::assertCount(1, $profile->messages());
    }
}
