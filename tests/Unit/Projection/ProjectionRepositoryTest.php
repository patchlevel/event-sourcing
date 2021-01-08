<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class ProjectionRepositoryTest extends TestCase
{
    use ProphecyTrait;

    public function testHandleWithNoProjections(): void
    {
        $projectionRepository = new ProjectionRepository([]);
        $projectionRepository->handle(ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        ));

        $this->expectNotToPerformAssertions();
    }

    public function testDrop(): void
    {
        $projection = $this->prophesize(Projection::class);
        $projection->drop()->shouldBeCalledOnce();

        $projectionRepository = new ProjectionRepository([$projection->reveal()]);
        $projectionRepository->drop();
    }

    public function testDropWithNoProjections(): void
    {
        $projectionRepository = new ProjectionRepository([]);
        $projectionRepository->drop();

        $this->expectNotToPerformAssertions();
    }
}
