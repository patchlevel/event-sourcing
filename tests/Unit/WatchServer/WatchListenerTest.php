<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\WatchServer;

use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\WatchServer\WatchListener;
use Patchlevel\EventSourcing\WatchServer\WatchServerClient;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class WatchListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testListener(): void
    {
        $event = ProfileVisited::raise(
            ProfileId::fromString('1'),
            ProfileId::fromString('1')
        )->recordNow(0);

        $client = $this->prophesize(WatchServerClient::class);
        $client->send($event)->shouldBeCalled();

        $listener = new WatchListener($client->reveal());
        $listener->__invoke($event);
    }
}
