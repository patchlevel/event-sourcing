<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\WatchServer;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\WatchServer\SendingFailed;
use Patchlevel\EventSourcing\WatchServer\WatchEventBus;
use Patchlevel\EventSourcing\WatchServer\WatchServerClient;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\WatchServer\WatchEventBus */
final class WatchEventBusTest extends TestCase
{
    use ProphecyTrait;

    public function testListener(): void
    {
        $message = new Message(new ProfileVisited(ProfileId::fromString('1')));

        $client = $this->prophesize(WatchServerClient::class);
        $client->send($message)->shouldBeCalled();

        $bus = new WatchEventBus($client->reveal());
        $bus->dispatch($message);
    }

    public function testIgnoreErrors(): void
    {
        $message = new Message(new ProfileVisited(ProfileId::fromString('1')));

        $client = $this->prophesize(WatchServerClient::class);
        $client->send($message)->shouldBeCalled()->willThrow(SendingFailed::class);

        $bus = new WatchEventBus($client->reveal());
        $bus->dispatch($message);
    }
}
