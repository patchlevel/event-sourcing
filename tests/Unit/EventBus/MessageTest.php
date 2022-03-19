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

        $event = new ProfileCreated(
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

        $event = new ProfileCreated(
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
}
