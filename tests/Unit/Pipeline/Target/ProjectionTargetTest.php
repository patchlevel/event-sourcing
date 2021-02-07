<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

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
        $this->markTestIncomplete('Testing not finished, needs discussion');

        $bucket = new EventBucket(
            Profile::class,
            ProfileCreated::raise(ProfileId::fromString('1'), Email::fromString('foo@test.com'))
        );

        $projectionRepository = $this->prophesize(Projection::class);
        $projectionRepository->handledEvents()->will(fn() => yield ProfileCreated::class => 'applyProfileCreated');

        $projectionTarget = new ProjectionTarget($projectionRepository->reveal());

        $projectionTarget->save($bucket);
    }
}
