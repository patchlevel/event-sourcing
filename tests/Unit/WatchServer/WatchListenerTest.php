<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\WatchServer;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\WatchServer\SendingFailed;
use Patchlevel\EventSourcing\WatchServer\WatchListener;
use Patchlevel\EventSourcing\WatchServer\WatchServerClient;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\WatchServer\WatchListener */
final class WatchListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testListener(): void
    {
        $message = new Message(new ProfileVisited(ProfileId::fromString('1')));

        $client = $this->prophesize(WatchServerClient::class);
        $client->send($message)->shouldBeCalled();

        $listener = new WatchListener($client->reveal());
        $listener->__invoke($message);
    }

    public function testIgnoreErrors(): void
    {
        $message = new Message(new ProfileVisited(ProfileId::fromString('1')));

        $client = $this->prophesize(WatchServerClient::class);
        $client->send($message)->shouldBeCalled()->willThrow(SendingFailed::class);

        $listener = new WatchListener($client->reveal());
        $listener->__invoke($message);
    }
}
