<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projectionist;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\SyncProjectionistEventBusWrapper;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Projection\Projectionist\SyncProjectionistEventBusWrapper */
final class SyncProjectionistEventBusWrapperTest extends TestCase
{
    use ProphecyTrait;

    public function testDispatch(): void
    {
        $this->markTestSkipped('skip until https://github.com/phpspec/prophecy/issues/568 is fixed');

        $messages = [
            Message::create(new ProfileVisited(ProfileId::fromString('1'))),
            Message::create(new ProfileVisited(ProfileId::fromString('2'))),
        ];

        $parentEventBus = $this->prophesize(EventBus::class);
        $parentEventBus->dispatch(...$messages)->shouldBeCalledOnce();
        $parentEventBus->reveal();

        $projectionist = $this->prophesize(Projectionist::class);
        $projectionist->run()->shouldBeCalledOnce();
        $projectionist->reveal();

        $eventBus = new SyncProjectionistEventBusWrapper(
            $parentEventBus->reveal(),
            $projectionist->reveal(),
        );

        $eventBus->dispatch(...$messages);
    }
}
