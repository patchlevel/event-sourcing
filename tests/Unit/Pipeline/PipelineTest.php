<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\InMemorySource;
use Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Pipeline */
final class PipelineTest extends TestCase
{
    public function testPipeline(): void
    {
        $messages = $this->messages();

        $source = new InMemorySource($messages);
        $target = new InMemoryTarget();
        $pipeline = new Pipeline($source, $target);

        self::assertSame(5, $pipeline->count());

        $pipeline->run();

        self::assertSame($messages, $target->messages());
    }

    public function testPipelineWithObserver(): void
    {
        $messages = $this->messages();

        $source = new InMemorySource($messages);
        $target = new InMemoryTarget();
        $pipeline = new Pipeline($source, $target);

        self::assertSame(5, $pipeline->count());

        $observer = new class {
            public bool $called = false;

            public function __invoke(Message $message): void
            {
                $this->called = true;
            }
        };

        $pipeline->run($observer->__invoke(...));

        self::assertSame($messages, $target->messages());
        self::assertTrue($observer->called);
    }

    public function testPipelineWithMiddleware(): void
    {
        $messages = $this->messages();

        $source = new InMemorySource($messages);
        $target = new InMemoryTarget();
        $pipeline = new Pipeline(
            $source,
            $target,
            [
                new ExcludeEventMiddleware([ProfileCreated::class]),
                new RecalculatePlayheadMiddleware(),
            ],
        );

        self::assertSame(5, $pipeline->count());

        $pipeline->run();

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

    /** @return list<Message> */
    private function messages(): array
    {
        return [
            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hallo@patchlevel.de'),
                ),
            )->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable())),

            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )->withHeader(new AggregateHeader('profile', '1', 2, new DateTimeImmutable())),

            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )->withHeader(new AggregateHeader('profile', '1', 3, new DateTimeImmutable())),

            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('2'),
                    Email::fromString('hallo@patchlevel.de'),
                ),
            )->withHeader(new AggregateHeader('profile', '2', 1, new DateTimeImmutable())),

            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('2'),
                ),
            )->withHeader(new AggregateHeader('profile', '2', 2, new DateTimeImmutable())),
        ];
    }
}
