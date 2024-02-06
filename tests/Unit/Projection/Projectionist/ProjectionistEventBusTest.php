<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projectionist;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistEventBus;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistEventBus */
final class ProjectionistEventBusTest extends TestCase
{
    use ProphecyTrait;

    public function testDispatch(): void
    {
        $this->markTestSkipped('skip until https://github.com/phpspec/prophecy/issues/568 is fixed');

        $messages = [
            Message::create(new ProfileVisited(ProfileId::fromString('1'))),
            Message::create(new ProfileVisited(ProfileId::fromString('2'))),
        ];

        $projectionist = $this->prophesize(Projectionist::class);
        $projectionist->run()->shouldBeCalledOnce();
        $projectionist->reveal();

        $eventBus = new ProjectionistEventBus(
            $projectionist->reveal(),
        );

        $eventBus->dispatch(...$messages);
    }
}
