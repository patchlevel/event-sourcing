<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Projection\ProjectionListener */
final class ProjectionListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testInvoke(): void
    {
        $profileCreated = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com')
        );

        $projectionRepository = $this->prophesize(ProjectionRepository::class);
        $projectionRepository->handle($profileCreated)->shouldBeCalledOnce();

        $projectionListener = new ProjectionListener($projectionRepository->reveal());
        $projectionListener($profileCreated);
    }
}
