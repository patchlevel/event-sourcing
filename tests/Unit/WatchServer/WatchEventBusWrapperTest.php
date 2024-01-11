<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\WatchServer;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\WatchServer\SendingFailed;
use Patchlevel\EventSourcing\WatchServer\WatchEventBusWrapper;
use Patchlevel\EventSourcing\WatchServer\WatchServerClient;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\WatchServer\WatchEventBusWrapper */
final class WatchEventBusWrapperTest extends TestCase
{
    use ProphecyTrait;

    public function testWrapper(): void
    {
        $message = new Message(new ProfileVisited(ProfileId::fromString('1')));

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch($message)->shouldBeCalled();

        $client = $this->prophesize(WatchServerClient::class);
        $client->send($message)->shouldBeCalled();

        $wrapper = new WatchEventBusWrapper(
            $eventBus->reveal(),
            $client->reveal(),
        );

        $wrapper->dispatch($message);
    }

    public function testIgnoreErrors(): void
    {
        $message = new Message(new ProfileVisited(ProfileId::fromString('1')));

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch($message)->shouldBeCalled();

        $client = $this->prophesize(WatchServerClient::class);
        $client->send($message)->shouldBeCalled()->willThrow(SendingFailed::class);

        $wrapper = new WatchEventBusWrapper(
            $eventBus->reveal(),
            $client->reveal(),
        );

        $wrapper->dispatch($message);
    }
}
