<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Middleware\DeleteEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\InMemorySource;
use Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    public function testPipeline(): void
    {
        $buckets = [
            new EventBucket(
                Profile::class,
                ProfileCreated::raise(
                    ProfileId::fromString('1'),
                    Email::fromString('d.a.badura@gmail.com')
                )->recordNow(0)
            ),
            new EventBucket(
                Profile::class,
                ProfileVisited::raise(
                    ProfileId::fromString('1'),
                    ProfileId::fromString('2')
                )->recordNow(1)
            ),
            new EventBucket(
                Profile::class,
                ProfileVisited::raise(
                    ProfileId::fromString('1'),
                    ProfileId::fromString('3')
                )->recordNow(2)
            ),
            new EventBucket(
                Profile::class,
                ProfileCreated::raise(
                    ProfileId::fromString('2'),
                    Email::fromString('d.a.badura@gmail.com')
                )->recordNow(0)
            ),
            new EventBucket(
                Profile::class,
                ProfileVisited::raise(
                    ProfileId::fromString('2'),
                    ProfileId::fromString('2')
                )->recordNow(1)
            ),
        ];

        $source = new InMemorySource($buckets);
        $target = new InMemoryTarget();
        $pipeline = new Pipeline($source, $target);

        self::assertEquals(5, $pipeline->count());

        $pipeline->run();

        self::assertEquals($buckets, $target->buckets());
    }

    public function testPipelineWithMiddleware(): void
    {
        $buckets = [
            new EventBucket(
                Profile::class,
                ProfileCreated::raise(
                    ProfileId::fromString('1'),
                    Email::fromString('d.a.badura@gmail.com')
                )->recordNow(0)
            ),
            new EventBucket(
                Profile::class,
                ProfileVisited::raise(
                    ProfileId::fromString('1'),
                    ProfileId::fromString('2')
                )->recordNow(1)
            ),
            new EventBucket(
                Profile::class,
                ProfileVisited::raise(
                    ProfileId::fromString('1'),
                    ProfileId::fromString('3')
                )->recordNow(2)
            ),
            new EventBucket(
                Profile::class,
                ProfileCreated::raise(
                    ProfileId::fromString('2'),
                    Email::fromString('d.a.badura@gmail.com')
                )->recordNow(0)
            ),
            new EventBucket(
                Profile::class,
                ProfileVisited::raise(
                    ProfileId::fromString('2'),
                    ProfileId::fromString('2')
                )->recordNow(1)
            ),
        ];

        $source = new InMemorySource($buckets);
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

        $resultBuckets = $target->buckets();

        self::assertCount(3, $resultBuckets);

        self::assertInstanceOf(ProfileVisited::class, $resultBuckets[0]->event());
        self::assertEquals('1', $resultBuckets[0]->event()->aggregateId());
        self::assertEquals(0, $resultBuckets[0]->event()->playhead());

        self::assertInstanceOf(ProfileVisited::class, $resultBuckets[1]->event());
        self::assertEquals('1', $resultBuckets[1]->event()->aggregateId());
        self::assertEquals(1, $resultBuckets[1]->event()->playhead());

        self::assertInstanceOf(ProfileVisited::class, $resultBuckets[2]->event());
        self::assertEquals('2', $resultBuckets[2]->event()->aggregateId());
        self::assertEquals(0, $resultBuckets[2]->event()->playhead());
    }
}
