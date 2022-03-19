<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\ApplyAttributeNotFound;
use Patchlevel\EventSourcing\Aggregate\DuplicateApplyMethod;
use Patchlevel\EventSourcing\Clock;
use Patchlevel\EventSourcing\EventBus\Message as EventBusMessage;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessageId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileInvalid;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSuppressAll;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Aggregate\AggregateRoot */
class AggregateRootTest extends TestCase
{
    public function setUp(): void
    {
        Clock::freeze(new DateTimeImmutable('2020-12-03 22:32:00'));
    }

    public function testApplyMethod(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $profile = Profile::createProfile($id, $email);

        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertEquals($id, $profile->id());
        self::assertEquals($email, $profile->email());
        self::assertSame(0, $profile->visited());

        $messages = $profile->releaseMessages();

        self::assertCount(1, $messages);

        $message = $messages[0];

        self::assertSame(Profile::class, $message->aggregateClass());
        self::assertSame('1', $message->aggregateId());
        self::assertSame(1, $message->playhead());
        self::assertEquals(new DateTimeImmutable('2020-12-03 22:32:00'), $message->recordedOn());

        $event = $message->event();

        self::assertInstanceOf(ProfileCreated::class, $event);
        self::assertEquals($id, $event->profileId());
        self::assertEquals($email, $event->email());
    }

    public function testCreateFromMessages(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise($id, $email);

        $messages = [
            new EventBusMessage(
                Profile::class,
                '1',
                1,
                $event
            ),
        ];

        $profile = Profile::createFromMessages($messages);

        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertEquals($id, $profile->id());
        self::assertEquals($email, $profile->email());
        self::assertSame(0, $profile->visited());

        $messages = $profile->releaseMessages();

        self::assertCount(0, $messages);
    }

    public function testMultipleApplyOnOneMethod(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $target = ProfileId::fromString('2');

        $profile = Profile::createProfile($id, $email);
        $profile->visitProfile($target);

        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(2, $profile->playhead());
        self::assertEquals($id, $profile->id());
        self::assertEquals($email, $profile->email());
        self::assertSame(1, $profile->visited());

        $messages = $profile->releaseMessages();

        self::assertCount(2, $messages);
    }

    public function testEventWithoutApplyMethod(): void
    {
        $this->expectException(ApplyAttributeNotFound::class);

        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $messageId = MessageId::fromString('2');

        $profile = Profile::createProfile($profileId, $email);

        $messages = $profile->releaseMessages();

        self::assertCount(1, $messages);
        self::assertSame(1, $profile->playhead());

        $message = $messages[0];

        self::assertSame(1, $message->playhead());

        $profile->publishMessage(
            Message::create(
                $messageId,
                'foo'
            )
        );
    }

    public function testSuppressEvent(): void
    {
        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $messageId = MessageId::fromString('2');

        $profile = Profile::createProfile($profileId, $email);

        $messages = $profile->releaseMessages();

        self::assertCount(1, $messages);
        self::assertSame(1, $profile->playhead());

        $message = $messages[0];

        self::assertSame(1, $message->playhead());

        $profile->deleteMessage($messageId);
    }

    public function testSuppressAll(): void
    {
        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $profile = ProfileWithSuppressAll::createProfile($profileId, $email);

        $messages = $profile->releaseMessages();

        self::assertCount(1, $messages);
        self::assertSame(1, $profile->playhead());

        $message = $messages[0];

        self::assertSame(1, $message->playhead());
    }

    public function testDuplicateApplyMethods(): void
    {
        $this->expectException(DuplicateApplyMethod::class);

        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        ProfileInvalid::createProfile($profileId, $email);
    }
}
