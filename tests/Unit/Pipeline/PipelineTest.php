<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget;
use Patchlevel\EventSourcing\Pipeline\Target\Target;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

use const INF;

/** @covers \Patchlevel\EventSourcing\Pipeline\Pipeline */
final class PipelineTest extends TestCase
{
    use ProphecyTrait;

    public function testPipeline(): void
    {
        $messages = $this->messages();

        $target = new InMemoryTarget();
        $pipeline = new Pipeline($target);

        $pipeline->run($messages);

        self::assertSame($messages, $target->messages());
    }

    public function testPipelineWithOneMiddleware(): void
    {
        $messages = $this->messages();

        $target = new InMemoryTarget();
        $pipeline = new Pipeline(
            $target,
            new ExcludeEventMiddleware([ProfileCreated::class]),
        );

        $pipeline->run($messages);

        $resultMessages = $target->messages();

        self::assertCount(3, $resultMessages);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[0]->event());
        self::assertSame('1', $resultMessages[0]->header(AggregateHeader::class)->aggregateId);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[1]->event());
        self::assertSame('1', $resultMessages[1]->header(AggregateHeader::class)->aggregateId);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[2]->event());
        self::assertSame('2', $resultMessages[2]->header(AggregateHeader::class)->aggregateId);
    }

    public function testPipelineWithMiddleware(): void
    {
        $messages = $this->messages();

        $target = new InMemoryTarget();
        $pipeline = new Pipeline(
            $target,
            [
                new ExcludeEventMiddleware([ProfileCreated::class]),
                new RecalculatePlayheadMiddleware(),
            ],
        );

        $pipeline->run($messages);

        $resultMessages = $target->messages();

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

    public function testBatching(): void
    {
        $messages = $this->messages();

        $target = $this->prophesize(Target::class);

        $target->save($messages[0], $messages[1], $messages[2])->shouldBeCalled();
        $target->save($messages[3], $messages[4])->shouldBeCalled();

        $pipeline = new Pipeline($target->reveal(), bufferSize: 3);

        $pipeline->run($messages);
    }

    public function testBatchingInf(): void
    {
        $messages = $this->messages();

        $target = $this->prophesize(Target::class);
        $target->save(...$messages)->shouldBeCalled();

        $pipeline = new Pipeline($target->reveal(), bufferSize: INF);

        $pipeline->run($messages);
    }

    public function testBatchingNothing(): void
    {
        $messages = $this->messages();

        $target = $this->prophesize(Target::class);

        $target->save($messages[0])->shouldBeCalled();
        $target->save($messages[1])->shouldBeCalled();
        $target->save($messages[2])->shouldBeCalled();
        $target->save($messages[3])->shouldBeCalled();
        $target->save($messages[4])->shouldBeCalled();

        $pipeline = new Pipeline($target->reveal(), bufferSize: 0);

        $pipeline->run($messages);
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
