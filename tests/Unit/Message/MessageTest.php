<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
    public function testMessage(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $message = new Message($event);

        self::assertEquals($event, $message->event());
        self::assertSame([], $message->headers());
    }

    public function testAllHeaders(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        $message = Message::create(new stdClass())
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(3))
            ->withHeader(new RecordedOnHeader($recordedAt))
            ->withHeader(new StreamStartHeader())
            ->withHeader(new ArchivedHeader());

        self::assertEquals(
            [
                new StreamNameHeader('profile-1'),
                new PlayheadHeader(3),
                new RecordedOnHeader($recordedAt),
                new StreamStartHeader(),
                new ArchivedHeader(),
            ],
            $message->headers(),
        );
    }

    public function testCreateWithEmptyHeaders(): void
    {
        $message = Message::createWithHeaders(new stdClass(), []);

        self::assertSame([], $message->headers());
    }

    public function testCreateWithAllHeaders(): void
    {
        $headers = [
            new StreamNameHeader('profile-1'),
            new PlayheadHeader(3),
            new RecordedOnHeader(new DateTimeImmutable('2020-05-06 13:34:24')),
            new StreamStartHeader(),
            new ArchivedHeader(),
        ];

        $message = Message::createWithHeaders(
            new stdClass(),
            $headers,
        );

        self::assertSame($headers, $message->headers());
    }

    public function testHasHeader(): void
    {
        $message = Message::create(new stdClass())
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(3))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-05-06 13:34:24')));

        self::assertTrue($message->hasHeader(StreamNameHeader::class));
        self::assertFalse($message->hasHeader(ArchivedHeader::class));
    }

    public function testChangeHeader(): void
    {
        $message = Message::create(new stdClass())
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-05-06 13:34:24')));

        self::assertSame(1, $message->header(PlayheadHeader::class)->playhead);

        $message = $message->withHeader(new PlayheadHeader(2));

        self::assertSame(2, $message->header(PlayheadHeader::class)->playhead);
    }

    public function testRemoveHeader(): void
    {
        $message = Message::create(new stdClass())
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(3))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-05-06 13:34:24')));

        $message = $message->removeHeader(PlayheadHeader::class);

        self::assertFalse($message->hasHeader(PlayheadHeader::class));
    }

    public function testHeaderNotFound(): void
    {
        $message = Message::create(new stdClass());

        $this->expectException(HeaderNotFound::class);
        /** @psalm-suppress UnusedMethodCall */
        $message->header(PlayheadHeader::class);
    }
}
