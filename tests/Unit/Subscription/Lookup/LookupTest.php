<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Lookup;

use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Lookup\Lookup;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Subscription\Lookup\Lookup */
final class LookupTest extends TestCase
{
    use ProphecyTrait;

    public function testMissingIndexHeader(): void
    {
        $store = $this->prophesize(Store::class);

        $event = new class () {
        };

        $message = new Message($event);

        $lookup = new Lookup(
            $store->reveal(),
            $message,
        );

        $this->expectException(HeaderNotFound::class);

        $lookup->fetchAll();
    }
}
