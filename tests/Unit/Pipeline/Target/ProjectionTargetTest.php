<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget */
class ProjectionTargetTest extends TestCase
{
    use ProphecyTrait;

    public function testSave(): void
    {
        $bucket = new EventBucket(
            Profile::class,
            1,
            ProfileCreated::raise(ProfileId::fromString('1'), Email::fromString('foo@test.com'))
        );

        $projectionRepository = $this->prophesize(ProjectionHandler::class);
        $projectionRepository->handle($bucket->event(), null)->shouldBeCalledOnce();

        $projectionTarget = new ProjectionTarget($projectionRepository->reveal());

        $projectionTarget->save($bucket);
    }
}
