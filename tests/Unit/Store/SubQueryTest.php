<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Generator;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SubQueryTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $query = new SubQuery();

        self::assertSame([], $query->tags);
        self::assertSame([], $query->events);
        self::assertSame(null, $query->streamName);
        self::assertFalse($query->onlyLastEvent);

        self::assertTrue($query->empty());
    }

    public function testNotEmpty(): void
    {
        $query = new SubQuery(['tag'], [ProfileCreated::class], 'foo', true);

        self::assertFalse($query->empty());
    }

    #[DataProvider('providerForMatch')]
    public function testMatch(SubQuery $subQuery, Message $message, bool $result): void
    {
        self::assertEquals($result, $subQuery->match($message));
    }

    /** @return Generator<string, array{SubQuery, Message, bool}> */
    public static function providerForMatch(): Generator
    {
        yield 'match always with empty subquery' => [
            new SubQuery(),
            self::messageFactory(ProfileCreated::class, ['test'], 'foo'),
            true,
        ];

        yield 'match always with empty subquery and only last event' => [
            new SubQuery(onlyLastEvent: true),
            self::messageFactory(ProfileCreated::class, ['test'], 'foo'),
            true,
        ];

        yield 'match with perfect message' => [
            new SubQuery(['test'], [ProfileCreated::class], 'foo'),
            self::messageFactory(ProfileCreated::class, ['test'], 'foo'),
            true,
        ];

        yield 'match with subset events' => [
            new SubQuery(['test'], [ProfileCreated::class, ProfileVisited::class], 'foo'),
            self::messageFactory(ProfileCreated::class, ['test'], 'foo'),
            true,
        ];

        yield 'match with more tags on message' => [
            new SubQuery(['test'], [ProfileCreated::class], 'foo'),
            self::messageFactory(ProfileCreated::class, ['test', 'other'], 'foo'),
            true,
        ];

        yield 'not match with wrong tag' => [
            new SubQuery(['test'], [ProfileCreated::class], 'foo'),
            self::messageFactory(ProfileCreated::class, ['other'], 'foo'),
            false,
        ];

        yield 'not match with less tags on message' => [
            new SubQuery(['test', 'other'], [ProfileCreated::class], 'foo'),
            self::messageFactory(ProfileCreated::class, ['test'], 'foo'),
            false,
        ];

        yield 'not match with wrong event' => [
            new SubQuery(['test'], [ProfileCreated::class], 'foo'),
            self::messageFactory(ProfileVisited::class, ['test'], 'foo'),
            false,
        ];

        yield 'not match with wrong stream' => [
            new SubQuery(['test'], [ProfileCreated::class], 'foo'),
            self::messageFactory(ProfileCreated::class, ['test'], 'bar'),
            false,
        ];

        yield 'not match with missing tag header' => [
            new SubQuery(['test'], [ProfileCreated::class], 'foo'),
            self::messageFactory(ProfileCreated::class, null, 'bar'),
            false,
        ];

        yield 'not match with missing steam header' => [
            new SubQuery(['test'], [ProfileCreated::class], 'foo'),
            self::messageFactory(ProfileCreated::class, ['test']),
            false,
        ];
    }

    #[DataProvider('providerForIncludes')]
    public function testIncludes(SubQuery $a, SubQuery $b, bool $result): void
    {
        self::assertEquals($result, $a->includes($b));
    }

    /** @return Generator<string, array{SubQuery, SubQuery, bool}> */
    public static function providerForIncludes(): Generator
    {
        yield 'empty includes empty' => [
            new SubQuery(),
            new SubQuery(),
            true,
        ];

        yield 'empty includes non-empty' => [
            new SubQuery(),
            new SubQuery(['tag']),
            true,
        ];

        yield 'non-empty does not include empty' => [
            new SubQuery(['tag']),
            new SubQuery(),
            false,
        ];

        yield 'same tags and events includes' => [
            new SubQuery(['tag'], [ProfileCreated::class]),
            new SubQuery(['tag'], [ProfileCreated::class]),
            true,
        ];

        yield 'different tags does not include' => [
            new SubQuery(['tag1'], [ProfileCreated::class]),
            new SubQuery(['tag2'], [ProfileCreated::class]),
            false,
        ];

        yield 'different events does not include' => [
            new SubQuery(['tag'], [ProfileCreated::class]),
            new SubQuery(['tag'], [ProfileVisited::class]),
            false,
        ];

        yield 'fewer tags and more events includes' => [
            new SubQuery(['tag1'], [ProfileCreated::class, ProfileVisited::class]),
            new SubQuery(['tag1', 'tag2'], [ProfileCreated::class]),
            true,
        ];

        yield 'more tags does not include' => [
            new SubQuery(['tag1', 'tag2'], [ProfileCreated::class]),
            new SubQuery(['tag1'], [ProfileCreated::class]),
            false,
        ];

        yield 'more events includes fewer events' => [
            new SubQuery(['tag1'], [ProfileCreated::class, ProfileVisited::class]),
            new SubQuery(['tag1'], [ProfileCreated::class]),
            true,
        ];

        yield 'fewer events does not include more events' => [
            new SubQuery(['tag1'], [ProfileCreated::class]),
            new SubQuery(['tag1'], [ProfileCreated::class, ProfileVisited::class]),
            false,
        ];

        yield 'all events includes specific events' => [
            new SubQuery(['tag1']),
            new SubQuery(['tag1'], [ProfileCreated::class]),
            true,
        ];

        yield 'specific events does not include all events' => [
            new SubQuery(['tag1'], [ProfileCreated::class]),
            new SubQuery(['tag1']),
            false,
        ];

        yield 'stream name equals includes' => [
            new SubQuery(streamName: 'foo'),
            new SubQuery(streamName: 'foo'),
            true,
        ];

        yield 'stream name not equals does not include' => [
            new SubQuery(streamName: 'foo'),
            new SubQuery(streamName: 'bar'),
            false,
        ];

        yield 'stream name does not include stream name not set' => [
            new SubQuery(streamName: 'foo'),
            new SubQuery(),
            false,
        ];

        yield 'no stream name does include stream name set' => [
            new SubQuery(),
            new SubQuery(streamName: 'foo'),
            true,
        ];

        yield 'only last event includes only last event' => [
            new SubQuery(onlyLastEvent: true),
            new SubQuery(onlyLastEvent: true),
            true,
        ];

        yield 'only last event does not include only last event false' => [
            new SubQuery(onlyLastEvent: true),
            new SubQuery(onlyLastEvent: false),
            false,
        ];

        yield 'only last event false does not include only last event true' => [
            new SubQuery(onlyLastEvent: false),
            new SubQuery(onlyLastEvent: true),
            true,
        ];
    }

    /**
     * @param class-string      $class
     * @param list<string>|null $tags
     */
    private static function messageFactory(string $class, array|null $tags = null, string|null $stream = null): Message
    {
        $message = match ($class) {
            ProfileCreated::class => Message::create(new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('s'),
            )),
            ProfileVisited::class => Message::create(new ProfileVisited(ProfileId::fromString('1'))),
            default => throw new InvalidArgumentException('unknown class'),
        };

        if ($tags !== null) {
            $message = $message->withHeader(new TagsHeader($tags));
        }

        if ($stream !== null) {
            $message = $message->withHeader(new StreamNameHeader($stream));
        }

        return $message;
    }
}
