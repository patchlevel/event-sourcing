<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\EventBus\ChainEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\EventBus\ChainEventBus */
final class ChainEventBusTest extends TestCase
{
    use ProphecyTrait;

    public function testDispatchEvent(): void
    {
        $message1 = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $message2 = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $eventBus1 = $this->prophesize(EventBus::class);
        $eventBus1->dispatch($message1, $message2)->shouldBeCalled();

        $eventBus2 = $this->prophesize(EventBus::class);
        $eventBus2->dispatch($message1, $message2)->shouldBeCalled();

        $chainEventBus = new ChainEventBus([$eventBus1->reveal(), $eventBus2->reveal()]);
        $chainEventBus->dispatch($message1, $message2);
    }
}
