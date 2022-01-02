<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Aggregate\ApplyAttributeNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessageId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithAttributeApply;
use PHPUnit\Framework\TestCase;

class AggregateRootWithAttributeApplyTest extends TestCase
{
    public function testApplyMethod(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('david.badura@patchlevel.de');

        $profile = ProfileWithAttributeApply::createProfile($id, $email);

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(1, $profile->playhead());
        self::assertEquals($id, $profile->id());
        self::assertEquals($email, $profile->email());

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertEquals(1, $event->playhead());
    }

    public function testMultipleApplyOnOneMethod(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('david.badura@patchlevel.de');

        $target = ProfileId::fromString('2');

        $profile = ProfileWithAttributeApply::createProfile($id, $email);
        $profile->visitProfile($target);

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(1, $profile->playhead());
        self::assertEquals($id, $profile->id());
        self::assertEquals($email, $profile->email());
        self::assertEquals(1, $profile->visited());

        $events = $profile->releaseEvents();

        self::assertCount(2, $events);
    }

    public function testEventWithoutApplyMethod(): void
    {
        $this->expectException(ApplyAttributeNotFound::class);

        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('david.badura@patchlevel.de');

        $messageId = MessageId::fromString('2');

        $profile = ProfileWithAttributeApply::createProfile($profileId, $email);

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
    }
}
