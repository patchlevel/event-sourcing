<?php

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ProjectionRepositoryTest extends TestCase
{
    use ProphecyTrait;

    public function testHandleEvent()
    {
        $projection = $this->prophesize(Projection::class);
        $projection::getHandledMessages()->willReturn([]);

        $repository = new ProjectionRepository([
            $projection->reveal()
        ]);

        $repository->handle(ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('d.m.badura@googlemail.com')
        ));
    }
}
