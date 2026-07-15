<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\DecisionModel;

use Patchlevel\EventSourcing\DecisionModel\StoreDecisionModelBuilder;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\IncrementProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoreDecisionModelBuilder::class)]
final class StoreDecisionModelBuilderTest extends TestCase
{
    public function testEmpty(): void
    {
        $store = $this->createMock(AppendStore::class);
        $store->expects($this->once())->method('query')->with(new Query())->willReturn(new Stream());

        $builder = new StoreDecisionModelBuilder($store);

        $state = $builder->build([]);

        self::assertEquals([], $state->state);
        self::assertEquals(new AppendCondition(new Query()), $state->appendCondition);
    }

    public function testWithProjections(): void
    {
        $store = $this->createMock(AppendStore::class);

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );
        $message = $message->withHeader(new TagsHeader(['foo']));

        $expectedQuery = new Query(
            new SubQuery(
                ['foo'],
                [ProfileCreated::class],
            ),
        );

        $store->expects($this->once())->method('query')->with($expectedQuery)->willReturn(new Stream([1 => $message]));

        $builder = new StoreDecisionModelBuilder($store);

        $state = $builder->build([
            'counter' => new IncrementProjection(0, ['foo']),
        ]);

        self::assertEquals(['counter' => 1], $state->state);

        self::assertEquals(new AppendCondition($expectedQuery, 1), $state->appendCondition);
    }

    public function testEmptyStream(): void
    {
        $store = $this->createMock(AppendStore::class);

        $expectedQuery = new Query(
            new SubQuery(
                ['foo'],
                [ProfileCreated::class],
            ),
        );

        $store->expects($this->once())->method('query')->with($expectedQuery)->willReturn(new Stream());

        $builder = new StoreDecisionModelBuilder($store);

        $state = $builder->build([
            'counter' => new IncrementProjection(0, ['foo']),
        ]);

        self::assertEquals(['counter' => 0], $state->state);

        self::assertEquals(new AppendCondition($expectedQuery, 0), $state->appendCondition);
    }
}
