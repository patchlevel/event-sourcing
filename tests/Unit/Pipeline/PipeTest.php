<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipe;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

use function iterator_to_array;

/** @covers \Patchlevel\EventSourcing\Pipeline\Pipe */
final class PipeTest extends TestCase
{
    use ProphecyTrait;

    public function testEmpty(): void
    {
        $stream = new Pipe([]);

        $result = iterator_to_array($stream);

        self::assertSame([], $result);
    }

    public function testWithMessages(): void
    {
        $messages = $this->messages();

        $stream = new Pipe($messages);

        $resultMessages = iterator_to_array($stream);

        self::assertSame($messages, $resultMessages);
    }

    public function testWithMiddlewares(): void
    {
        $messages = $this->messages();

        $stream = new Pipe(
            $messages,
            [
                new ExcludeEventMiddleware([ProfileCreated::class]),
                new RecalculatePlayheadMiddleware(),
            ],
        );

        $resultMessages = iterator_to_array($stream);

        self::assertCount(3, $resultMessages);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[0]->event());
        self::assertSame('1', $resultMessages[0]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(1, $resultMessages[0]->header(AggregateHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[1]->event());
        self::assertSame('1', $resultMessages[1]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(2, $resultMessages[1]->header(AggregateHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[2]->event());
        self::assertSame('2', $resultMessages[2]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(1, $resultMessages[2]->header(AggregateHeader::class)->playhead);
    }

    public function testAppendMiddleware(): void
    {
        $messages = $this->messages();

        $stream = new Pipe(
            $messages,
            [
                new ExcludeEventMiddleware([ProfileCreated::class]),
            ],
        );

        $stream = $stream->appendMiddleware(
            new RecalculatePlayheadMiddleware(),
        );

        $resultMessages = iterator_to_array($stream);

        self::assertCount(3, $resultMessages);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[0]->event());
        self::assertSame('1', $resultMessages[0]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(1, $resultMessages[0]->header(AggregateHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[1]->event());
        self::assertSame('1', $resultMessages[1]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(2, $resultMessages[1]->header(AggregateHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[2]->event());
        self::assertSame('2', $resultMessages[2]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(1, $resultMessages[2]->header(AggregateHeader::class)->playhead);
    }

    public function testPrependMiddleware(): void
    {
        $messages = $this->messages();

        $stream = new Pipe(
            $messages,
            [
                new RecalculatePlayheadMiddleware(),
            ],
        );

        $stream = $stream->prependMiddleware(
            new ExcludeEventMiddleware([ProfileCreated::class]),
        );

        $resultMessages = iterator_to_array($stream);

        self::assertCount(3, $resultMessages);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[0]->event());
        self::assertSame('1', $resultMessages[0]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(1, $resultMessages[0]->header(AggregateHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[1]->event());
        self::assertSame('1', $resultMessages[1]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(2, $resultMessages[1]->header(AggregateHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[2]->event());
        self::assertSame('2', $resultMessages[2]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(1, $resultMessages[2]->header(AggregateHeader::class)->playhead);
    }

    /** @return list<Message> */
    private function messages(): array
    {
        return [
            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hallo@patchlevel.de'),
                ),
            )
                ->withHeader(new AggregateHeader(
                    'profile',
                    '1',
                    1,
                    new DateTimeImmutable(),
                )),
            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )
                ->withHeader(new AggregateHeader(
                    'profile',
                    '1',
                    2,
                    new DateTimeImmutable(),
                )),
            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )
                ->withHeader(new AggregateHeader(
                    'profile',
                    '1',
                    3,
                    new DateTimeImmutable(),
                )),

            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('2'),
                    Email::fromString('hallo@patchlevel.de'),
                ),
            )
                ->withHeader(new AggregateHeader(
                    'profile',
                    '2',
                    1,
                    new DateTimeImmutable(),
                )),

            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('2'),
                ),
            )
                ->withHeader(new AggregateHeader(
                    'profile',
                    '2',
                    2,
                    new DateTimeImmutable(),
                )),
        ];
    }
}
