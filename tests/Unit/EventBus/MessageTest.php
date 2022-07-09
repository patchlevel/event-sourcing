<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\HeaderNotFound;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\EventBus\Message */
final class MessageTest extends TestCase
{
    public function testEmptyMessage(): void
    {
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
    }

    public function testCreateMessageWithHeader(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = new ProfileCreated(
            $id,
            $email
        );

        $message = Message::create($event)
            ->withAggregateClass(Profile::class)
            ->withAggregateId('1')
            ->withPlayhead(1)
            ->withRecordedOn($recordedAt);

        self::assertEquals($event, $message->event());
        self::assertSame(Profile::class, $message->aggregateClass());
        self::assertSame('1', $message->aggregateId());
        self::assertSame(1, $message->playhead());
        self::assertEquals($recordedAt, $message->recordedOn());
    }

    public function testChangeHeader(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = new ProfileCreated(
            $id,
            $email
        );

        $message = Message::create($event)
            ->withAggregateClass(Profile::class)
            ->withAggregateId('1')
            ->withPlayhead(1)
            ->withRecordedOn($recordedAt);

        $message = $message->withPlayhead(2);

        self::assertSame(Profile::class, $message->aggregateClass());
        self::assertSame('1', $message->aggregateId());
        self::assertSame(2, $message->playhead());
        self::assertEquals($recordedAt, $message->recordedOn());
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
        $message->aggregateClass();
    }

    public function testCustomHeaders(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = new ProfileCreated(
            $id,
            $email
        );

        $message = Message::create($event)
            ->withAggregateClass(Profile::class)
            ->withAggregateId('1')
            ->withPlayhead(1)
            ->withRecordedOn($recordedAt)
            ->withCustomHeader('custom-field', 'foo-bar');

        self::assertEquals(
            ['custom-field' => 'foo-bar'],
            $message->customHeaders()
        );
    }
}
