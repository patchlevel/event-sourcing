<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\NameChanged;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\EventBus\DefaultEventBus */
final class DefaultEventBusTest extends TestCase
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

        $consumer = $this->prophesize(Consumer::class);
        $consumer->consume($message)->shouldBeCalled();

        $eventBus = new DefaultEventBus($consumer->reveal());
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

        $consumer = $this->prophesize(Consumer::class);
        $consumer->consume($message1)->shouldBeCalled();
        $consumer->consume($message2)->shouldBeCalled();

        $eventBus = new DefaultEventBus($consumer->reveal());
        $eventBus->dispatch($message1, $message2);
    }

    public function testMultipleMessagesAddingNewEventInListener(): void
    {
        $messageA = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $messageB = new Message(
            new ProfileVisited(
                ProfileId::fromString('1'),
            ),
        );

        $messageC = new Message(
            new NameChanged(
                'name',
            ),
        );

        $consumer = $this->prophesize(Consumer::class);
        $eventBus = new DefaultEventBus($consumer->reveal());

        $consumer->consume($messageA)->shouldBeCalled()->will(static function () use ($eventBus, $messageC): void {
            $eventBus->dispatch($messageC);
        });
        $consumer->consume($messageB)->shouldBeCalled();
        $consumer->consume($messageC)->shouldBeCalled();

        $eventBus->dispatch($messageA, $messageB);
    }
}
