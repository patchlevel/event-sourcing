<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\DecisionModel;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\DecisionModel\DecisionAlreadyAppended;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
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

use function iterator_to_array;

#[CoversClass(Decision::class)]
final class DecisionTest extends TestCase
{
    public function testWithoutRead(): void
    {
        $decision = new Decision(new InMemoryStore());

        self::assertNull($decision->appendCondition());
    }

    public function testReadEmptyStore(): void
    {
        $decision = new Decision(new InMemoryStore());

        self::assertSame(0, $decision->read(new IncrementProjection(0, ['a'])));
        self::assertEquals(
            new AppendCondition(new Query(new SubQuery(['a'], [ProfileCreated::class])), 0),
            $decision->appendCondition(),
        );
    }

    public function testAfterIsHead(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->created('1', ['a']),
            $this->created('2', ['b']),
        );

        $decision = new Decision($store);

        self::assertSame(1, $decision->read(new IncrementProjection(0, ['a'])));
        self::assertSame(2, $decision->appendCondition()?->after);
    }

    public function testAfterIncludesEventsAfterHead(): void
    {
        $message = $this->created('1', ['a']);

        $store = $this->createMock(AppendStore::class);
        $store->method('head')->willReturn(0);
        $store->method('query')->willReturn(new Stream([3 => $message]));

        $decision = new Decision($store);

        self::assertSame(1, $decision->read(new IncrementProjection(0, ['a'])));
        self::assertSame(3, $decision->appendCondition()?->after);
    }

    public function testChainedReads(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->created('1', ['a']),
            $this->created('2', ['b']),
            $this->created('3', ['b']),
        );

        $decision = new Decision($store);

        self::assertSame(1, $decision->read(new IncrementProjection(0, ['a'])));
        self::assertSame(2, $decision->read(new IncrementProjection(0, ['b'])));
        self::assertEquals(
            new AppendCondition(
                new Query(
                    new SubQuery(['a'], [ProfileCreated::class]),
                    new SubQuery(['b'], [ProfileCreated::class]),
                ),
                3,
            ),
            $decision->appendCondition(),
        );
    }

    public function testHeadIsOnlyLoadedForTheFirstRead(): void
    {
        $store = $this->createMock(AppendStore::class);
        $store->expects($this->once())->method('head')->willReturn(5);
        $store->expects($this->exactly(2))->method('query')->willReturn(new Stream());

        $decision = new Decision($store);
        $decision->read(new IncrementProjection(0, ['a']));
        $decision->read(new IncrementProjection(0, ['b']));

        self::assertSame(5, $decision->appendCondition()?->after);
    }

    public function testLaterReadsDoNotRaiseAfter(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->created('1', ['a']),
        );

        $decision = new Decision($store);
        $decision->read(new IncrementProjection(0, ['a']));

        $store->save(
            $this->created('2', ['b']),
        );

        self::assertSame(1, $decision->read(new IncrementProjection(0, ['b'])));
        self::assertSame(1, $decision->appendCondition()?->after);
    }

    public function testReadWithoutSubQueryMatchesEverything(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->created('1', ['a']),
            $this->visited('1', ['b']),
        );

        $decision = new Decision($store);
        $decision->read(new IncrementProjection(0, ['a']));

        $count = $decision->read(new /** @implements Projection<int> */ class implements Projection {
            public function initialState(): int
            {
                return 0;
            }

            /** @param int $state */
            public function apply(mixed $state, Message $message): int
            {
                return $state + 1;
            }
        });

        self::assertSame(2, $count);
        self::assertEquals(new AppendCondition(new Query(), 2), $decision->appendCondition());
    }

    public function testReadMultipleProjectionsWithOneQuery(): void
    {
        $store = $this->createMock(AppendStore::class);
        $store->expects($this->once())->method('head')->willReturn(0);
        $store->expects($this->once())->method('query')
            ->with(new Query(
                new SubQuery(['a'], [ProfileCreated::class]),
                new SubQuery(['b'], [ProfileCreated::class]),
            ))
            ->willReturn(new Stream([
                1 => $this->created('1', ['a']),
                2 => $this->created('2', ['b']),
                3 => $this->created('3', ['b']),
            ]));

        $decision = new Decision($store);

        self::assertSame(
            ['a' => 1, 'b' => 2],
            $decision->read([
                'a' => new IncrementProjection(0, ['a']),
                'b' => new IncrementProjection(0, ['b']),
            ]),
        );
        self::assertSame(3, $decision->appendCondition()?->after);
    }

    public function testReadListOfProjections(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->created('1', ['a']),
            $this->created('2', ['b']),
        );

        $decision = new Decision($store);

        [$a, $b] = $decision->read([
            new IncrementProjection(0, ['a']),
            new IncrementProjection(10, ['b']),
        ]);

        self::assertSame(1, $a);
        self::assertSame(11, $b);
    }

    public function testReadEmptyArray(): void
    {
        $store = $this->createMock(AppendStore::class);
        $store->expects($this->never())->method('head');
        $store->expects($this->never())->method('query');

        $decision = new Decision($store);

        self::assertSame([], $decision->read([]));
        self::assertNull($decision->appendCondition());
    }

    public function testReadMultipleAndSingleProjections(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->created('1', ['a']),
            $this->created('2', ['b']),
            $this->created('3', ['c']),
        );

        $decision = new Decision($store);
        $decision->read([
            'a' => new IncrementProjection(0, ['a']),
            'b' => new IncrementProjection(0, ['b']),
        ]);
        $decision->read(new IncrementProjection(0, ['c']));

        self::assertEquals(
            new AppendCondition(
                new Query(
                    new SubQuery(['a'], [ProfileCreated::class]),
                    new SubQuery(['b'], [ProfileCreated::class]),
                    new SubQuery(['c'], [ProfileCreated::class]),
                ),
                3,
            ),
            $decision->appendCondition(),
        );
    }

    public function testReadSingleProjectionFollowedByMultiple(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->created('1', ['a']),
            $this->created('2', ['b']),
            $this->created('3', ['c']),
        );

        $decision = new Decision($store);

        self::assertSame(1, $decision->read(new IncrementProjection(0, ['a'])));

        $store->save($this->created('4', ['b']));

        self::assertSame(
            ['b' => 2, 'c' => 1],
            $decision->read([
                'b' => new IncrementProjection(0, ['b']),
                'c' => new IncrementProjection(0, ['c']),
            ]),
        );
        self::assertEquals(
            new AppendCondition(
                new Query(
                    new SubQuery(['a'], [ProfileCreated::class]),
                    new SubQuery(['b'], [ProfileCreated::class]),
                    new SubQuery(['c'], [ProfileCreated::class]),
                ),
                3,
            ),
            $decision->appendCondition(),
        );
    }

    public function testReadMultipleWithCheckpointOnlyForOneProjection(): void
    {
        $store = new InMemoryStore();
        $store->save(
            Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')))
                ->withHeader(new TagsHeader(['profile-1'])),
            $this->visited('1', ['profile-1']),
            $this->split(10, ['profile-1']),
            $this->visited('1', ['profile-1']),
        );

        $decision = new Decision($store);

        self::assertSame(
            ['created' => 1, 'visits' => 11],
            $decision->read([
                'created' => new IncrementProjection(0, ['profile-1']),
                'visits' => new VisitsProjection(['profile-1']),
            ]),
        );
    }

    public function testAppend(): void
    {
        $store = new InMemoryStore();

        $decision = new Decision($store);
        $decision->read(new IncrementProjection(0, ['profile:1']));
        $decision->append([new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'))]);

        $messages = iterator_to_array($store->load());

        self::assertCount(1, $messages);
        self::assertSame('main', $messages[1]->header(StreamNameHeader::class)->streamName);
    }

    public function testAppendWithStreamName(): void
    {
        $store = new InMemoryStore();

        $decision = new Decision($store);
        $decision->append([new ProfileVisited(ProfileId::fromString('1'))], 'profile');

        $messages = iterator_to_array($store->load());

        self::assertSame('profile', $messages[1]->header(StreamNameHeader::class)->streamName);
    }

    public function testAppendFailsIfReadEventsChanged(): void
    {
        $store = new InMemoryStore();

        $decision = new Decision($store);
        $decision->read(new IncrementProjection(0, ['a']));

        $store->save($this->created('1', ['a']));

        $this->expectException(AppendConditionNotMet::class);

        $decision->append([new ProfileVisited(ProfileId::fromString('1'))]);
    }

    public function testAppendTwice(): void
    {
        $decision = new Decision(new InMemoryStore());
        $decision->append([new ProfileVisited(ProfileId::fromString('1'))]);

        $this->expectException(DecisionAlreadyAppended::class);

        $decision->append([new ProfileVisited(ProfileId::fromString('1'))]);
    }

    public function testReadAfterAppend(): void
    {
        $decision = new Decision(new InMemoryStore());
        $decision->append([new ProfileVisited(ProfileId::fromString('1'))]);

        $this->expectException(DecisionAlreadyAppended::class);

        $decision->read(new IncrementProjection(0, ['a']));
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

        $decision = new Decision($store);

        self::assertSame(
            ['a' => 'second@patchlevel.de', 'ab' => 'first@patchlevel.de'],
            $decision->read([
                'a' => new LastEmailProjection(['a']),
                'ab' => new LastEmailProjection(['a', 'b']),
            ]),
        );
    }

    public function testCheckpoint(): void
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

        $decision = new Decision($store);

        self::assertSame(12, $decision->read(new VisitsProjection(['profile-1'])));
        self::assertSame(6, $decision->appendCondition()?->after);
    }

    public function testCheckpointReadsFromLastCheckpointEvent(): void
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

            public function head(): int
            {
                return 3;
            }
        };

        $decision = new Decision($store);

        self::assertSame(10, $decision->read(new VisitsProjection(['profile-1'])));
        self::assertEquals(new AppendCondition($expectedQuery, 3), $decision->appendCondition());
        self::assertEquals(
            [
                [new Query(new SubQuery(['profile-1'], [SplittingEvent::class], null, true)), 0],
                [$expectedQuery, 3],
            ],
            $store->calls,
        );
    }

    public function testCheckpointWithoutCheckpointEvent(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->visited('1', ['profile-1']),
            $this->visited('1', ['profile-1']),
        );

        $decision = new Decision($store);

        self::assertSame(2, $decision->read(new VisitsProjection(['profile-1'])));
    }

    public function testSplitStreamAloneIsNoCheckpoint(): void
    {
        $store = new InMemoryStore();
        $store->save(
            $this->split(10, ['profile-1']),
            $this->split(20, ['profile-1']),
        );

        $decision = new Decision($store);

        $splits = $decision->read(new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            #[Apply]
            public function applySplittingEvent(int $state, SplittingEvent $event): int
            {
                return $state + 1;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return ['profile-1'];
            }
        });

        self::assertSame(2, $splits);
    }

    /** @param list<string> $tags */
    private function created(string $id, array $tags): Message
    {
        return Message::create(new ProfileCreated(ProfileId::fromString($id), Email::fromString('info@patchlevel.de')))
            ->withHeader(new TagsHeader($tags));
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
