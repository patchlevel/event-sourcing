<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\EventBus\Psr14EventBus;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;

/** @covers \Patchlevel\EventSourcing\EventBus\Psr14EventBus */
final class Psr14EventBusTest extends TestCase
{
    use ProphecyTrait;

    public function testDispatchEvent(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->dispatch($message)->shouldBeCalled();

        $eventBus = new Psr14EventBus($eventDispatcher->reveal());
        $eventBus->dispatch($message);
    }

    public function testDispatchMultipleMessages(): void
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

        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->dispatch($message1)->shouldBeCalled();
        $eventDispatcher->dispatch($message2)->shouldBeCalled();

        $eventBus = new Psr14EventBus($eventDispatcher->reveal());
        $eventBus->dispatch($message1, $message2);
    }
}
