<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\ApplyMethodNotFound;
use Patchlevel\EventSourcing\Aggregate\MetadataNotPossible;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\DuplicateApplyMethod;
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
final class AggregateRootTest extends TestCase
{
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

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);

        $event = $events[0];

        self::assertInstanceOf(ProfileCreated::class, $event);
        self::assertEquals($id, $event->profileId);
        self::assertEquals($email, $event->email);
    }

    public function testCreateFromMessages(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $profile = Profile::createFromEvents([new ProfileCreated($id, $email)]);

        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertEquals($id, $profile->id());
        self::assertEquals($email, $profile->email());
        self::assertSame(0, $profile->visited());

        $messages = $profile->releaseEvents();

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

        $events = $profile->releaseEvents();

        self::assertCount(2, $events);
    }

    public function testEventWithoutApplyMethod(): void
    {
        $this->expectException(ApplyMethodNotFound::class);

        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $messageId = MessageId::fromString('2');

        $profile = Profile::createProfile($profileId, $email);

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        self::assertSame(1, $profile->playhead());

        $profile->publishMessage(
            Message::create(
                $messageId,
                'foo',
            ),
        );
    }

    public function testSuppressEvent(): void
    {
        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $messageId = MessageId::fromString('2');

        $profile = Profile::createProfile($profileId, $email);

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        self::assertSame(1, $profile->playhead());

        $profile->deleteMessage($messageId);
    }

    public function testSuppressAll(): void
    {
        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $profile = ProfileWithSuppressAll::createProfile($profileId, $email);

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        self::assertSame(1, $profile->playhead());
    }

    public function testDuplicateApplyMethods(): void
    {
        $this->expectException(DuplicateApplyMethod::class);

        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        ProfileInvalid::createProfile($profileId, $email);
    }

    public function testMetadata(): void
    {
        $metadata = Profile::metadata();

        self::assertInstanceOf(AggregateRootMetadata::class, $metadata);
    }

    public function testMetadataNotPossible(): void
    {
        $this->expectException(MetadataNotPossible::class);

        AggregateRoot::metadata();
    }
}
