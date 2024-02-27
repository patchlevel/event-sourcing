<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use DateTimeImmutable;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\EventBus\HeaderNotFound;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\EventBus\Message */
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

    public function testCreateMessageWithAggregateHeader(): void
    {
        $message = Message::create(new class {
        })
            ->withAggregateName('profile');

        self::assertSame('profile', $message->header(AggregateHeader::class)->aggregateName);
    }

    public function testCreateMessageWithAggregateIdHeader(): void
    {
        $message = Message::create(new class {
        })
            ->withAggregateId('1');

        self::assertSame('1', $message->header(AggregateHeader::class)->aggregateId);
    }

    public function testCreateMessageWithPlayheadHeader(): void
    {
        $message = Message::create(new class {
        })
            ->withPlayhead(1);

        self::assertSame(1, $message->header(AggregateHeader::class)->playhead);
    }

    public function testCreateMessageWithRecordedOnHeader(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        $message = Message::create(new class {
        })
            ->withRecordedOn($recordedAt);

        self::assertEquals($recordedAt, $message->header(AggregateHeader::class)->recordedOn);
    }

    public function testCreateMessageWithCustomHeader(): void
    {
        $message = Message::create(new class {
        })
            ->withHeader('custom-field', 'foo-bar');

        self::assertEquals('foo-bar', $message->header('custom-field'));
        self::assertEquals(
            ['custom-field' => 'foo-bar'],
            $message->customHeaders(),
        );
    }

    public function testCreateMessageWithCustomHeaders(): void
    {
        $message = Message::create(new class {
        })
            ->withHeaders(['custom-field' => 'foo-bar']);

        self::assertEquals('foo-bar', $message->header('custom-field'));
        self::assertEquals(
            ['custom-field' => 'foo-bar'],
            $message->customHeaders(),
        );
    }

    public function testCreateMessageWithNewStreamStartHeader(): void
    {
        $message = Message::create(new class {
        })
            ->withNewStreamStart(true);

        self::assertTrue($message->newStreamStart());
    }

    public function testCreateMessageWithArchivedHeader(): void
    {
        $message = Message::create(new class {
        })
            ->withArchived(true);

        self::assertTrue($message->archived());
    }

    public function testChangeHeader(): void
    {
        $message = Message::create(new class {
        })
            ->withPlayhead(1);
        self::assertSame(1, $message->header(AggregateHeader::class)->playhead);

        $message = $message->withPlayhead(2);
        self::assertSame(2, $message->header(AggregateHeader::class)->playhead);
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
            ->withAggregateName('profile')
            ->withAggregateId('1')
            ->withPlayhead(3)
            ->withRecordedOn($recordedAt)
            ->withArchived(true)
            ->withNewStreamStart(true)
            ->withHeader('foo', 'bar');

        self::assertSame(
            [
                'aggregateName' => 'profile',
                'aggregateId' => '1',
                'playhead' => 3,
                'recordedOn' => $recordedAt,
                'archived' => true,
                'newStreamStart' => true,
                'foo' => 'bar',
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
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');
        $message = Message::createWithHeaders(
            new class {
            },
            [
                'foo' => 'bar',
                'aggregateName' => 'profile',
                'aggregateId' => '1',
                'playhead' => 3,
                'recordedOn' => $recordedAt,
                'newStreamStart' => true,
                'archived' => true,
            ],
        );

        self::assertSame(
            [
                'foo' => 'bar',
                'aggregateName' => 'profile',
                'aggregateId' => '1',
                'playhead' => 3,
                'recordedOn' => $recordedAt,
                'newStreamStart' => true,
                'archived' => true,
            ],
            $message->headers(),
        );
    }

    public function testHeaderNotFound(): void
    {
        $message = Message::create(new class {
        });

        $this->expectException(HeaderNotFound::class);
        /** @psalm-suppress UnusedMethodCall */
        $message->header('Foo');
    }
}
