<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Store\PipelineStore;
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
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com'))
        );

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $pipelineStore = $this->prophesize(PipelineStore::class);
        $pipelineStore->stream(0)->willReturn($generatorFactory());

        $source = new StoreSource($pipelineStore->reveal());

        $generator = $source->load();

        self::assertSame($message, $generator->current());

        $generator->next();

        self::assertSame(null, $generator->current());
    }

    public function testLoadWithFromIndex(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com'))
        );

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $pipelineStore = $this->prophesize(PipelineStore::class);
        $pipelineStore->stream(1)->willReturn($generatorFactory());

        $source = new StoreSource($pipelineStore->reveal(), 1);

        $generator = $source->load();

        self::assertSame($message, $generator->current());

        $generator->next();

        self::assertSame(null, $generator->current());
    }

    public function testCount(): void
    {
        $pipelineStore = $this->prophesize(PipelineStore::class);
        $pipelineStore->count(0)->willReturn(1);

        $source = new StoreSource($pipelineStore->reveal());

        self::assertSame(1, $source->count());
    }

    public function testCountWithFromIndex(): void
    {
        $pipelineStore = $this->prophesize(PipelineStore::class);
        $pipelineStore->count(1)->willReturn(0);

        $source = new StoreSource($pipelineStore->reveal(), 1);

        self::assertSame(0, $source->count());
    }
}
