<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Middleware\UntilEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;

class UntilEventMiddlewareTest extends TestCase
{
    public function testPositive(): void
    {
        $until = new DateTimeImmutable('2020-02-02 00:00:00');

        $middleware = new UntilEventMiddleware($until);

        $bucket = new EventBucket(
            Profile::class,
            AggregateChanged::deserialize([
                'aggregateId' => '1',
                'playhead' => 0,
                'event' => ProfileCreated::class,
                'payload' => '{}',
                'recordedOn' => new DateTimeImmutable('2020-02-01 00:00:00'),
            ])
        );

        $result = $middleware($bucket);

        self::assertEquals([$bucket], $result);
    }

    public function testNegative(): void
    {
        $until = new DateTimeImmutable('2020-01-01 00:00:00');

        $middleware = new UntilEventMiddleware($until);

        $bucket = new EventBucket(
            Profile::class,
            AggregateChanged::deserialize([
                'aggregateId' => '1',
                'playhead' => 0,
                'event' => ProfileCreated::class,
                'payload' => '{}',
                'recordedOn' => new DateTimeImmutable('2020-02-01 00:00:00'),
            ])
        );

        $result = $middleware($bucket);

        self::assertEquals([], $result);
    }

    public function testNullEdgeCase(): void
    {
        $until = new DateTimeImmutable('2020-01-01 00:00:00');

        $middleware = new UntilEventMiddleware($until);

        $bucket = new EventBucket(
            Profile::class,
            AggregateChanged::deserialize([
                'aggregateId' => '1',
                'playhead' => 0,
                'event' => ProfileCreated::class,
                'payload' => '{}',
                'recordedOn' => null,
            ])
        );

        $result = $middleware($bucket);

        self::assertEquals([], $result);
    }
}
