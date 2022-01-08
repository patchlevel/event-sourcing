<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessageId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithNonStrictApply;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Aggregate\AggregateRoot */
class AggregateRootWithNonStrictApplyTest extends TestCase
{
    public function testApplyMethod(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $profile = ProfileWithNonStrictApply::createProfile($id, $email);

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(1, $profile->playhead());
        self::assertEquals($id, $profile->id());
        self::assertEquals($email, $profile->email());

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertEquals(1, $event->playhead());
    }

    public function testEventWithoutApplyMethod(): void
    {
        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $messageId = MessageId::fromString('2');

        $profile = ProfileWithNonStrictApply::createProfile($profileId, $email);

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        self::assertEquals(1, $profile->playhead());
        $event = $events[0];
        self::assertEquals(1, $event->playhead());

        $profile->publishMessage(
            Message::create(
                $messageId,
                'foo'
            )
        );

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertEquals(2, $event->playhead());
    }
}
