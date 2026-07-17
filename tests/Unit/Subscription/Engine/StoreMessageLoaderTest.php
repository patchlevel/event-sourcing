<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoreMessageLoader::class)]
final class StoreMessageLoaderTest extends TestCase
{
    public function testLoadWithoutStartIndex(): void
    {
        $stream = new Stream();

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria())
            ->willReturn($stream);

        $loader = new StoreMessageLoader($store);

        self::assertSame($stream, $loader->load(null, []));
    }

    public function testLoadWithStartIndex(): void
    {
        $stream = new Stream();

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(new FromIndexCriterion(5)))
            ->willReturn($stream);

        $loader = new StoreMessageLoader($store);

        self::assertSame($stream, $loader->load(5, []));
    }

    public function testLastIndex(): void
    {
        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(null, 1, null, true)
            ->willReturn(new Stream([5 => $message]));

        $loader = new StoreMessageLoader($store);

        self::assertSame(5, $loader->lastIndex());
    }

    public function testLastIndexEmptyStream(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(null, 1, null, true)
            ->willReturn(new Stream());

        $loader = new StoreMessageLoader($store);

        self::assertSame(0, $loader->lastIndex());
    }
}
