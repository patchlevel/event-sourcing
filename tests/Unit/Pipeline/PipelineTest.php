<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Pipeline\Middleware\DeleteEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\InMemorySource;
use Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    public function testPipeline(): void
    {
        $events = [
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(0),
            ProfileVisited::raise(
                ProfileId::fromString('1'),
                ProfileId::fromString('2')
            )->recordNow(1),
            ProfileVisited::raise(
                ProfileId::fromString('1'),
                ProfileId::fromString('3')
            )->recordNow(2),
            ProfileCreated::raise(
                ProfileId::fromString('2'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(0),
            ProfileVisited::raise(
                ProfileId::fromString('2'),
                ProfileId::fromString('2')
            )->recordNow(1),
        ];

        $source = new InMemorySource($events);
        $target = new InMemoryTarget();
        $pipeline = new Pipeline($source, $target);

        self::assertEquals(5, $pipeline->count());

        $pipeline->run();

        self::assertEquals($events, $target->events());
    }

    public function testPipelineWithMiddleware(): void
    {
        $events = [
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(0),
            ProfileVisited::raise(
                ProfileId::fromString('1'),
                ProfileId::fromString('2')
            )->recordNow(1),
            ProfileVisited::raise(
                ProfileId::fromString('1'),
                ProfileId::fromString('3')
            )->recordNow(2),
            ProfileCreated::raise(
                ProfileId::fromString('2'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(0),
            ProfileVisited::raise(
                ProfileId::fromString('2'),
                ProfileId::fromString('2')
            )->recordNow(1),
        ];

        $source = new InMemorySource($events);
        $target = new InMemoryTarget();
        $pipeline = new Pipeline(
            $source,
            $target,
            [
                new DeleteEventMiddleware([ProfileCreated::class]),
                new RecalculatePlayheadMiddleware(),
            ]
        );

        self::assertEquals(5, $pipeline->count());

        $pipeline->run();

        $resultEvents = $target->events();

        self::assertCount(3, $resultEvents);

        self::assertInstanceOf(ProfileVisited::class, $resultEvents[0]);
        self::assertEquals('1', $resultEvents[0]->aggregateId());
        self::assertEquals(0, $resultEvents[0]->playhead());

        self::assertInstanceOf(ProfileVisited::class, $resultEvents[1]);
        self::assertEquals('1', $resultEvents[1]->aggregateId());
        self::assertEquals(1, $resultEvents[1]->playhead());

        self::assertInstanceOf(ProfileVisited::class, $resultEvents[2]);
        self::assertEquals('2', $resultEvents[2]->aggregateId());
        self::assertEquals(0, $resultEvents[2]->playhead());
    }
}
