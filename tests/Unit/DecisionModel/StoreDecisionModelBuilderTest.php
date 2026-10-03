<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\DecisionModel;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DecisionModel\StoreDecisionModelBuilder;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\IncrementProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\LastEmailProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\SplittingEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\VisitsProjection;
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

    public function testOnlyLastEventProjectionsWithOverlappingTags(): void
    {
        $store = new InMemoryStore();
        $store->save(
            Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('first@patchlevel.de')))
                ->withHeader(new TagsHeader(['a', 'b'])),
            Message::create(new ProfileCreated(ProfileId::fromString('2'), Email::fromString('second@patchlevel.de')))
                ->withHeader(new TagsHeader(['a'])),
        );

        $builder = new StoreDecisionModelBuilder($store);

        $state = $builder->build([
            'a' => new LastEmailProjection(['a']),
            'ab' => new LastEmailProjection(['a', 'b']),
        ]);

        self::assertEquals(
            ['a' => 'second@patchlevel.de', 'ab' => 'first@patchlevel.de'],
            $state->state,
        );
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

    public function testSplitStream(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->visited('1', ['profile-1']),
            $this->visited('1', ['profile-1']),
            $this->split(10, ['profile-1']),
            $this->visited('1', ['profile-1']),
            $this->split(99, ['profile-2']),
            $this->visited('1', ['profile-1']),
        );

        $builder = new StoreDecisionModelBuilder($store);

        $state = $builder->build([
            'visits' => new VisitsProjection(['profile-1']),
        ]);

        self::assertSame(['visits' => 12], $state->state);
        self::assertSame(6, $state->appendCondition->after);
    }

    public function testSplitStreamReadsFromLastSplitEvent(): void
    {
        $splitMessage = $this->split(10, ['profile-1'])->withHeader(new IndexHeader(3));

        $expectedQuery = new Query(
            new SubQuery(['profile-1'], [ProfileVisited::class, SplittingEvent::class]),
        );

        $store = new class ($splitMessage) implements AppendStore {
            /** @var list<array{Query, int}> */
            public array $calls = [];

            public function __construct(
                private readonly Message $splitMessage,
            ) {
            }

            /** @param iterable<Message> $messages */
            public function append(iterable $messages, AppendCondition|null $appendCondition = null): void
            {
            }

            public function query(Query $query, int $from = 0): Stream
            {
                $this->calls[] = [$query, $from];

                return new Stream([3 => $this->splitMessage]);
            }
        };

        $builder = new StoreDecisionModelBuilder($store);

        $state = $builder->build([
            'visits' => new VisitsProjection(['profile-1']),
        ]);

        self::assertSame(['visits' => 10], $state->state);
        self::assertEquals(new AppendCondition($expectedQuery, 3), $state->appendCondition);
        self::assertEquals(
            [
                [new Query(new SubQuery(['profile-1'], [SplittingEvent::class], null, true)), 0],
                [$expectedQuery, 3],
            ],
            $store->calls,
        );
    }

    public function testSplitStreamWithoutSplitEvent(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->visited('1', ['profile-1']),
            $this->visited('1', ['profile-1']),
        );

        $builder = new StoreDecisionModelBuilder($store);

        $state = $builder->build([
            'visits' => new VisitsProjection(['profile-1']),
        ]);

        self::assertSame(['visits' => 2], $state->state);
    }

    public function testSplitStreamWithProjectionWithoutSplitEvents(): void
    {
        $store = new InMemoryStore();
        $store->save(
            Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')))
                ->withHeader(new TagsHeader(['profile-1'])),
            $this->visited('1', ['profile-1']),
            $this->split(10, ['profile-1']),
            $this->visited('1', ['profile-1']),
        );

        $builder = new StoreDecisionModelBuilder($store);

        $state = $builder->build([
            'created' => new IncrementProjection(0, ['profile-1']),
            'visits' => new VisitsProjection(['profile-1']),
        ]);

        self::assertSame(['created' => 1, 'visits' => 11], $state->state);
    }

    public function testSplitStreamOptOut(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->split(10, ['profile-1']),
            $this->split(20, ['profile-1']),
        );

        $builder = new StoreDecisionModelBuilder($store);

        $state = $builder->build([
            'splits' => new class extends BasicProjection {
                public function initialState(): int
                {
                    return 0;
                }

                #[Apply]
                public function applySplittingEvent(int $state, SplittingEvent $event): int
                {
                    return $state + 1;
                }

                /** @return list<class-string> */
                public function splitEvents(): array
                {
                    return [];
                }

                /** @return list<string> */
                protected function tagFilter(): array
                {
                    return ['profile-1'];
                }
            },
        ]);

        self::assertSame(['splits' => 2], $state->state);
    }

    /** @param list<string> $tags */
    private function visited(string $id, array $tags): Message
    {
        return Message::create(new ProfileVisited(ProfileId::fromString($id)))
            ->withHeader(new TagsHeader($tags));
    }

    /** @param list<string> $tags */
    private function split(int $visits, array $tags): Message
    {
        return Message::create(new SplittingEvent(Email::fromString('info@patchlevel.de'), $visits))
            ->withHeader(new TagsHeader($tags));
    }
}
