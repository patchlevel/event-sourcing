<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionHandlerTarget;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Target\ProjectionHandlerTarget */
class ProjectionHandlerTargetTest extends TestCase
{
    use ProphecyTrait;

    public function testSave(): void
    {
        $bucket = new EventBucket(
            Profile::class,
            1,
            ProfileCreated::raise(ProfileId::fromString('1'), Email::fromString('foo@test.com'))
        );

        $projectionHandler = $this->prophesize(ProjectionHandler::class);
        $projectionHandler->handle($bucket->event())->shouldBeCalledOnce();

        $projectionHandlerTarget = new ProjectionHandlerTarget($projectionHandler->reveal());

        $projectionHandlerTarget->save($bucket);
    }
}
