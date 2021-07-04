<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Middleware\FilterEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

class FilterEventMiddlewareTest extends TestCase
{
    public function testPositive(): void
    {
        $middleware = new FilterEventMiddleware(static function (AggregateChanged $aggregateChanged) {
            return $aggregateChanged instanceof ProfileCreated;
        });

        $bucket = new EventBucket(
            Profile::class,
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(0)
        );

        $result = $middleware($bucket);

        self::assertEquals([$bucket], $result);
    }

    public function testNegative(): void
    {
        $middleware = new FilterEventMiddleware(static function (AggregateChanged $aggregateChanged) {
            return $aggregateChanged instanceof ProfileCreated;
        });

        $bucket = new EventBucket(
            Profile::class,
            ProfileVisited::raise(
                ProfileId::fromString('1'),
                ProfileId::fromString('2')
            )->recordNow(0)
        );

        $result = $middleware($bucket);

        self::assertEquals([], $result);
    }
}
