<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Pipe;
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator;
use Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

#[CoversClass(Pipe::class)]
final class PipeTest extends TestCase
{
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

    public function testToArray(): void
    {
        $messages = $this->messages();

        $stream = new Pipe($messages);

        self::assertSame($messages, $stream->toArray());
    }

    public function testWithOneMiddleware(): void
    {
        $messages = $this->messages();

        $stream = new Pipe(
            $messages,
            new ExcludeEventTranslator([ProfileCreated::class]),
        );

        $resultMessages = iterator_to_array($stream);

        self::assertCount(3, $resultMessages);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[0]->event());
        self::assertSame('1', $resultMessages[0]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(2, $resultMessages[0]->header(AggregateHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[1]->event());
        self::assertSame('1', $resultMessages[1]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(3, $resultMessages[1]->header(AggregateHeader::class)->playhead);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[2]->event());
        self::assertSame('2', $resultMessages[2]->header(AggregateHeader::class)->aggregateId);
        self::assertSame(2, $resultMessages[2]->header(AggregateHeader::class)->playhead);
    }

    public function testWithMiddlewares(): void
    {
        $messages = $this->messages();

        $stream = new Pipe(
            $messages,
            new ExcludeEventTranslator([ProfileCreated::class]),
            new RecalculatePlayheadTranslator(),
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
