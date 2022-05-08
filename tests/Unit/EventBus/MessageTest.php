<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock;
use Patchlevel\EventSourcing\EventBus\HeaderNotFound;
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

    public function testEmptyMessage(): void
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
            $event
        );

        self::assertEquals($event, $message->event());
        self::assertEquals([], $message->headers());
    }

    public function testCreateMessageWithHeader(): void
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
            $event,
            [
                Message::HEADER_AGGREGATE_CLASS => Profile::class,
                Message::HEADER_AGGREGATE_ID => '1',
                Message::HEADER_PLAYHEAD => 1,
                Message::HEADER_RECORDED_ON => $recordedAt,
            ]
        );

        self::assertEquals($event, $message->event());
        self::assertEquals(
            [
                Message::HEADER_AGGREGATE_CLASS => Profile::class,
                Message::HEADER_AGGREGATE_ID => '1',
                Message::HEADER_PLAYHEAD => 1,
                Message::HEADER_RECORDED_ON => $recordedAt,
            ],
            $message->headers()
        );
        self::assertSame(Profile::class, $message->aggregateClass());
        self::assertSame('1', $message->aggregateId());
        self::assertSame(1, $message->playhead());
        self::assertEquals($recordedAt, $message->recordedOn());
    }

    public function testChangeHeader(): void
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
            $event,
            [
                Message::HEADER_AGGREGATE_CLASS => Profile::class,
                Message::HEADER_AGGREGATE_ID => '1',
                Message::HEADER_PLAYHEAD => 1,
                Message::HEADER_RECORDED_ON => $recordedAt,
            ]
        );

        $message = $message->withHeader(Message::HEADER_PLAYHEAD, 2);
        $message = $message->withHeader('custom-field', 'foo-bar');

        self::assertEquals(
            [
                Message::HEADER_AGGREGATE_CLASS => Profile::class,
                Message::HEADER_AGGREGATE_ID => '1',
                Message::HEADER_PLAYHEAD => 2,
                Message::HEADER_RECORDED_ON => $recordedAt,
                'custom-field' => 'foo-bar',
            ],
            $message->headers()
        );

        self::assertSame(Profile::class, $message->aggregateClass());
        self::assertSame('1', $message->aggregateId());
        self::assertSame(2, $message->playhead());
        self::assertEquals($recordedAt, $message->recordedOn());

        self::assertEquals(2, $message->header(Message::HEADER_PLAYHEAD));
        self::assertEquals('foo-bar', $message->header('custom-field'));
    }

    public function testHeaderNotFound(): void
    {
        $this->expectException(HeaderNotFound::class);

        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $message = new Message(
            new ProfileCreated(
                $id,
                $email
            )
        );

        /** @psalm-suppress UnusedMethodCall */
        $message->header(Message::HEADER_AGGREGATE_ID);
    }
}
