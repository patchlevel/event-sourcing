<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Source;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Source\StoreSource */
final class StoreSourceTest extends TestCase
{
    use ProphecyTrait;

    public function testLoad(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );

        $stream = new ArrayStream([$message]);

        $pipelineStore = $this->prophesize(Store::class);
        $pipelineStore->load($this->criteria())->willReturn($stream);

        $source = new StoreSource($pipelineStore->reveal());

        self::assertSame($stream, $source->load());
    }

    public function testLoadWithFromIndex(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );

        $stream = new ArrayStream([$message]);

        $pipelineStore = $this->prophesize(Store::class);
        $pipelineStore->load($this->criteria(1))->willReturn($stream);

        $source = new StoreSource($pipelineStore->reveal(), 1);

        self::assertSame($stream, $source->load());
    }

    public function testCount(): void
    {
        $pipelineStore = $this->prophesize(Store::class);
        $pipelineStore->count($this->criteria())->willReturn(1);

        $source = new StoreSource($pipelineStore->reveal());

        self::assertSame(1, $source->count());
    }

    public function testCountWithFromIndex(): void
    {
        $pipelineStore = $this->prophesize(Store::class);
        $pipelineStore->count($this->criteria(1))->willReturn(0);

        $source = new StoreSource($pipelineStore->reveal(), 1);

        self::assertSame(0, $source->count());
    }

    private function criteria(int $fromIndex = 0): Criteria
    {
        return (new CriteriaBuilder())->fromIndex($fromIndex)->build();
    }
}
