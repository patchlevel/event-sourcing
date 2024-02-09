<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use DateTimeImmutable;
use Generator;
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

        self::assertSame('profile', $message->aggregateName());
    }

    public function testCreateMessageWithAggregateIdHeader(): void
    {
        $message = Message::create(new class {
        })
            ->withAggregateId('1');

        self::assertSame('1', $message->aggregateId());
    }

    public function testCreateMessageWithPlayheadHeader(): void
    {
        $message = Message::create(new class {
        })
            ->withPlayhead(1);

        self::assertSame(1, $message->playhead());
    }

    public function testCreateMessageWithRecordedOnHeader(): void
    {
        $recordedAt = new DateTimeImmutable('2020-05-06 13:34:24');

        $message = Message::create(new class {
        })
            ->withRecordedOn($recordedAt);

        self::assertEquals($recordedAt, $message->recordedOn());
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
        self::assertSame(1, $message->playhead());

        $message = $message->withPlayhead(2);
        self::assertSame(2, $message->playhead());
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

    #[DataProvider('provideHeaderNotFound')]
    public function testHeaderNotFound(string $headerName): void
    {
        $message = Message::create(new class {
        });

        $this->expectException(HeaderNotFound::class);
        /** @psalm-suppress UnusedMethodCall */
        $message->{$headerName}();
    }

    /** @return Generator<string, array{string}> */
    public static function provideHeaderNotFound(): Generator
    {
        yield 'aggregateName' => ['aggregateName'];
        yield 'aggregateId' => ['aggregateId'];
        yield 'playhead' => ['playhead'];
        yield 'recordedOn' => ['recordedOn'];
        yield 'archived' => ['archived'];
        yield 'newStreamStart' => ['newStreamStart'];
    }

    public function testCustomHeaderNotFound(): void
    {
        $message = Message::create(new class {
        });

        $this->expectException(HeaderNotFound::class);
        /** @psalm-suppress UnusedMethodCall */
        $message->header('foo');
    }
}
