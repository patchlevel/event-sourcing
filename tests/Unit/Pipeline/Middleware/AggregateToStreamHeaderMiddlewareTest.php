<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\AggregateToStreamHeaderMiddleware;
use Patchlevel\EventSourcing\Store\StreamHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\AggregateToStreamHeaderMiddleware */
final class AggregateToStreamHeaderMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    public function testMissingHeader(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $middleware = new AggregateToStreamHeaderMiddleware();

        $result = $middleware($message);

        self::assertEquals([$message], $result);
    }

    public function testMigrateHeader(): void
    {
        $aggregateHeader = new AggregateHeader(
            'profile',
            '1',
            1,
            new DateTimeImmutable(),
        );

        $message = (new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        ))->withHeader($aggregateHeader);

        $middleware = new AggregateToStreamHeaderMiddleware();

        $result = $middleware($message);

        self::assertCount(1, $result);

        $message = $result[0];

        self::assertFalse($message->hasHeader(AggregateHeader::class));
        self::assertTrue($message->hasHeader(StreamHeader::class));

        $streamHeader = $message->header(StreamHeader::class);

        self::assertEquals($aggregateHeader->recordedOn, $streamHeader->recordedOn);
        self::assertEquals('profile-1', $streamHeader->streamName);
        self::assertEquals(1, $streamHeader->playhead);
    }
}
