<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
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
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('foo@bar.com')
            )
        );

        $projectionRepository = $this->prophesize(ProjectionHandler::class);
        $projectionRepository->handle($message)->shouldBeCalledOnce();

        $projectionListener = new ProjectionListener($projectionRepository->reveal());
        $projectionListener($message);
    }
}
