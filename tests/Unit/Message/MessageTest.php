<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\Message */
final class MessageTest extends TestCase
{
    public function testEmptyMessage(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $message = new Message($event);

        self::assertEquals($event, $message->event());
    }

    public function testEmptyAllHeaders(): void
    {
        $message = Message::create(new class {
        });

        self::assertSame([], $message->headers());
    }

    public function testAllHeaders(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        $message = Message::create(new class {
        })
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                3,
                $recordedAt,
            ))
            ->withHeader(new StreamStartHeader())
            ->withHeader(new ArchivedHeader());

        self::assertEquals(
            [
                new AggregateHeader(
                    'profile',
                    '1',
                    3,
                    $recordedAt,
                ),
                new StreamStartHeader(),
                new ArchivedHeader(),
            ],
            $message->headers(),
        );
    }

    public function testCreateWithEmptyHeaders(): void
    {
        $message = Message::createWithHeaders(new class {
        }, []);

        self::assertSame([], $message->headers());
    }

    public function testCreateWithAllHeaders(): void
    {
        $headers = [
            new AggregateHeader(
                'profile',
                '1',
                3,
                new DateTimeImmutable('2020-05-06 13:34:24'),
            ),
            new StreamStartHeader(),
            new ArchivedHeader(),
        ];

        $message = Message::createWithHeaders(
            new class {
            },
            $headers,
        );

        self::assertSame($headers, $message->headers());
    }

    public function testChangeHeader(): void
    {
        $message = Message::create(new class {
        })->withHeader(new AggregateHeader(
            'profile',
            '1',
            1,
            new DateTimeImmutable('2020-05-06 13:34:24'),
        ));
        self::assertSame(1, $message->header(AggregateHeader::class)->playhead);

        $message = $message->withHeader(new AggregateHeader(
            'profile',
            '1',
            2,
            new DateTimeImmutable('2020-05-06 13:34:24'),
        ));
        self::assertSame(2, $message->header(AggregateHeader::class)->playhead);
    }

    public function testHeaderNotFound(): void
    {
        $message = Message::create(new class {
        });

        $this->expectException(HeaderNotFound::class);
        /** @psalm-suppress UnusedMethodCall */
        $message->header(AggregateHeader::class);
    }
}
