<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\TagCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Store\Events\ExternEvent;
use Patchlevel\EventSourcing\Tests\Integration\Store\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\PhpunitHelper;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

use function iterator_to_array;
use function json_decode;
use function sprintf;

#[CoversNothing]
final class TaggableDoctrineDbalStoreTest extends TestCase
{
    use PhpunitHelper;

    private Connection $connection;
    private TaggableDoctrineDbalStore $store;

    private ClockInterface $clock;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();

        $this->clock = new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00'));

        $this->store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $this->store,
        );

        $schemaDirector->create();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testSave(): void
    {
        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00'))),
        ];

        $this->store->save(...$messages);

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result1['stream']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result1['event_payload'], true),
        );

        $result2 = $result[1];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result2['stream']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result2['event_payload'], true),
        );
    }

    public function testSaveWithIndex(): void
    {
        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new IndexHeader(1)),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00')))
                ->withHeader(new IndexHeader(42)),
        ];

        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
            config: ['keep_index' => true],
        );

        $store->save(...$messages);

        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
        );

        $store->save(
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(3))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00'))),
        );

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(3, $result);

        $result1 = $result[0];

        self::assertEquals(1, $result1['id']);
        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result1['stream']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result1['event_payload'], true),
        );

        $result2 = $result[1];

        self::assertEquals(42, $result2['id']);
        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result2['stream']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result2['event_payload'], true),
        );

        $result3 = $result[2];

        self::assertEquals(43, $result3['id']);
        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result3['stream']);
        self::assertEquals('3', $result3['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result3['recorded_on']);
        self::assertEquals('profile.created', $result3['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result3['event_payload'], true),
        );
    }

    public function testSaveWithOnlyStreamName(): void
    {
        $messages = [
            Message::create(new ExternEvent('test 1'))
                ->withHeader(new StreamNameHeader('extern')),
            Message::create(new ExternEvent('test 2'))
                ->withHeader(new StreamNameHeader('extern')),
        ];

        $this->store->save(...$messages);

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals('extern', $result1['stream']);
        self::assertEquals(null, $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('extern', $result1['event_name']);
        self::assertEquals(
            ['message' => 'test 1'],
            json_decode($result1['event_payload'], true),
        );

        $result2 = $result[1];

        self::assertEquals('extern', $result2['stream']);
        self::assertEquals(null, $result2['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result2['recorded_on']);
        self::assertEquals('extern', $result2['event_name']);
        self::assertEquals(
            ['message' => 'test 2'],
            json_decode($result2['event_payload'], true),
        );
    }

    public function testSaveWithTransactional(): void
    {
        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00'))),
        ];

        $this->store->transactional(function () use ($messages): void {
            $this->store->save(...$messages);
        });

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result1['stream']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result1['event_payload'], true),
        );

        $result2 = $result[1];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result2['stream']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result2['event_payload'], true),
        );
    }

    public function testArchive(): void
    {
        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new EventIdHeader('1'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new EventIdHeader('2'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00'))),
        ];

        $this->store->save(...$messages);
        $this->store->archive(
            new Criteria(
                new StreamCriterion(sprintf('profile-%s', $profileId->toString())),
                new ToPlayheadCriterion(2),
            ),
        );

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store ORDER BY id');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result1['stream']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result1['event_payload'], true),
        );

        self::assertEquals('1', $result1['archived']);

        $result2 = $result[1];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result2['stream']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result2['event_payload'], true),
        );

        self::assertEquals('0', $result2['archived']);
    }

    public function testUniqueStreamNameAndPlayheadConstraint(): void
    {
        $this->expectException(UniqueConstraintViolation::class);

        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
        ];

        $this->store->save(...$messages);
    }

    public function testUniqueEventIdConstraint(): void
    {
        $this->expectException(UniqueConstraintViolation::class);

        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new EventIdHeader('1'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new EventIdHeader('1'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
        ];

        $this->store->save(...$messages);
    }

    public function testSave10000Messages(): void
    {
        $profileId = ProfileId::generate();

        $messages = [];

        for ($i = 1; $i <= 10000; $i++) {
            $messages[] = Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader($i))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')));
        }

        $this->store->save(...$messages);

        /** @var int $result */
        $result = $this->connection->fetchFirstColumn('SELECT COUNT(*) FROM event_store')[0];

        self::assertEquals(10000, $result);
    }

    public function testLoad(): void
    {
        $profileId = ProfileId::generate();

        $message = Message::create(new ProfileCreated($profileId, 'test'))
            ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')));

        $this->store->save($message);

        $stream = null;

        try {
            $stream = $this->store->load();

            self::assertSame(1, $stream->index());
            self::assertSame(0, $stream->position());

            $loadedMessage = $stream->current();

            self::assertInstanceOf(Message::class, $loadedMessage);
            self::assertNotSame($message, $loadedMessage);
            self::assertEquals($message->event(), $loadedMessage->event());
            self::assertEquals(
                $message->header(StreamNameHeader::class)->streamName,
                $loadedMessage->header(StreamNameHeader::class)->streamName,
            );
            self::assertEquals(
                $message->header(PlayheadHeader::class)->playhead,
                $loadedMessage->header(PlayheadHeader::class)->playhead,
            );
            self::assertEquals(
                $message->header(RecordedOnHeader::class)->recordedOn,
                $loadedMessage->header(RecordedOnHeader::class)->recordedOn,
            );
        } finally {
            $stream?->close();
        }
    }

    public function testLoadWithWildcard(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId1->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId2->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo')),
        ];

        $this->store->save(...$messages);

        $stream = null;

        try {
            $stream = $this->store->load(new Criteria(new StreamCriterion('profile-*')));

            $messages = iterator_to_array($stream);

            self::assertCount(2, $messages);
        } finally {
            $stream?->close();
        }

        $stream = null;

        try {
            $stream = $this->store->load(new Criteria(new StreamCriterion('*-*')));

            $messages = iterator_to_array($stream);

            self::assertCount(2, $messages);
        } finally {
            $stream?->close();
        }
    }

    public function testTags(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader([
                    'profile:' . $profileId1->toString(),
                    'profile:' . $profileId2->toString(),
                ])),
        ];

        $this->store->save(...$messages);

        $stream = null;

        try {
            $stream = $this->store->load(
                new Criteria(
                    new TagCriterion(['profile:' . $profileId1->toString()]),
                ),
            );

            $messages = iterator_to_array($stream);

            self::assertCount(2, $messages);

            self::assertEquals(
                ['profile:' . $profileId1->toString()],
                $messages[0]->header(TagsHeader::class)->tags,
            );

            self::assertEquals(
                ['profile:' . $profileId1->toString(), 'profile:' . $profileId2->toString()],
                $messages[1]->header(TagsHeader::class)->tags,
            );
        } finally {
            $stream?->close();
        }
    }

    public function testAppendWithoutAppendCondition(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader([
                    'profile:' . $profileId1->toString(),
                    'profile:' . $profileId2->toString(),
                ])),
        ];

        $this->store->append($messages);
        self::assertStreamEquals($messages, $this->store->load());
    }

    public function testQueryTags(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));

        $this->store->append([$message1, $message2, $message3]);

        $stream = $this->store->query(new Query(
            new SubQuery(['profile:' . $profileId1->toString()]),
        ));

        self::assertStreamEquals([$message1, $message3], $stream);
    }

    public function testQueryEvents(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));

        $this->store->append([$message1, $message2, $message3]);

        $stream = $this->store->query(new Query(
            new SubQuery(events: [ProfileCreated::class]),
        ));

        self::assertStreamEquals([$message1, $message2], $stream);
    }

    public function testQueryStream(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('baz'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));

        $this->store->append([$message1, $message2, $message3]);

        $stream = $this->store->query(new Query(
            new SubQuery(streamName: 'foo'),
            new SubQuery(streamName: 'baz'),
        ));

        self::assertStreamEquals([$message1, $message3], $stream);
    }

    public function testQueryTagAndEvent(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));

        $this->store->append([$message1, $message2, $message3]);

        $stream = $this->store->query(new Query(
            new SubQuery(['profile:' . $profileId1->toString()], [ProfileCreated::class]),
        ));

        self::assertStreamEquals([$message1], $stream);
    }

    public function testOnlyLastEvent(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));

        $this->store->append([$message1, $message2]);

        $stream = $this->store->query(
            new Query(
                new SubQuery(
                    events: [ProfileCreated::class],
                    onlyLastEvent: true,
                ),
            ),
        );

        self::assertStreamEquals([$message2], $stream);
    }

    public function testComplexQuery(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('main'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));
        $message4 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([]));

        $this->store->append([$message1, $message2, $message3, $message4]);

        $stream = $this->store->query(new Query(
            new SubQuery(['profile:' . $profileId1->toString()], [ProfileCreated::class]),
            new SubQuery(['profile:' . $profileId2->toString()]),
            new SubQuery(events: [ExternEvent::class]),
            new SubQuery(streamName: 'foo'),
        ));

        self::assertStreamEquals([$message1, $message2, $message3, $message4], $stream);
    }

    public function testAppendRaceCondition(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
        ];

        $this->store->append($messages);

        $messages = [
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader([
                    'profile:' . $profileId1->toString(),
                    'profile:' . $profileId2->toString(),
                ])),
        ];

        $this->expectException(AppendConditionNotMet::class);

        $this->store->append(
            $messages,
            new AppendCondition(
                new Query(new SubQuery(['profile:' . $profileId1->toString()])),
                0,
            ),
        );
    }

    public function testAppendRaceConditionEmptyQuery(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
        ];

        $this->store->append($messages);

        $messages = [
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader([
                    'profile:' . $profileId1->toString(),
                    'profile:' . $profileId2->toString(),
                ])),
        ];

        $this->expectException(AppendConditionNotMet::class);

        $this->store->append(
            $messages,
            new AppendCondition(
                new Query(),
                0,
            ),
        );
    }

    public function testAppendWithEmptyQuery(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
        ];

        $this->store->append($messages);

        $message = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));

        $this->store->append(
            [$message],
            new AppendCondition(
                new Query(),
                2,
            ),
        );

        self::assertStreamEquals([...$messages, $message], $this->store->load());
    }

    public function testStreams(): void
    {
        $profileId = ProfileId::fromString('0190e47e-77e9-7b90-bf62-08bbf0ab9b4b');

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo')),
        ];

        $this->store->save(...$messages);

        $streams = $this->store->streams();

        self::assertEquals([
            'foo',
            'profile-0190e47e-77e9-7b90-bf62-08bbf0ab9b4b',
        ], $streams);
    }

    public function testRemove(): void
    {
        $profileId = ProfileId::fromString('0190e47e-77e9-7b90-bf62-08bbf0ab9b4b');

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo')),
        ];

        $this->store->save(...$messages);

        $streams = $this->store->streams();

        self::assertEquals([
            'foo',
            'profile-0190e47e-77e9-7b90-bf62-08bbf0ab9b4b',
        ], $streams);

        $this->store->remove(new Criteria(new StreamCriterion('profile-*')));

        $streams = $this->store->streams();

        self::assertEquals(['foo'], $streams);
    }
}
