<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ProjectionTargetTest extends TestCase
{
    use ProphecyTrait;

    public function testSave(): void
    {
        $bucket = new EventBucket(
            Profile::class,
            ProfileCreated::raise(ProfileId::fromString('1'), Email::fromString('foo@test.com'))
        );

        $projectionRepository = new class implements Projection {
            public static ?AggregateChanged $handledEvent = null;

            public function handledEvents(): iterable
            {
                yield ProfileCreated::class => 'applyProfileCreated';
            }

            public function applyProfileCreated(ProfileCreated $event): void
            {
                self::$handledEvent = $event;
            }

            public function create(): void {}
            public function drop(): void {}
        };

        $projectionTarget = new ProjectionTarget($projectionRepository);
        $projectionTarget->save($bucket);

        self::assertSame($bucket->event(), $projectionRepository::$handledEvent);
    }
}
