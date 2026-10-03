<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\DecisionModel;

use Patchlevel\EventSourcing\DecisionModel\StoreEventAppender;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Serializer\EventTagExtractor;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoreEventAppender::class)]
final class StoreEventAppenderTest extends TestCase
{
    public function testAppendNothing(): void
    {
        $store = $this->createMock(AppendStore::class);
        $store->expects($this->never())->method('append');

        $appender = new StoreEventAppender($store);

        $appender->append([]);
    }

    public function testAppendEventsWithoutCondition(): void
    {
        $expectedEvent = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $expectedMessage = (new Message($expectedEvent))
            ->withHeader(new TagsHeader([]))
            ->withHeader(new StreamNameHeader('main'));

        $store = $this->createMock(AppendStore::class);
        $store->expects($this->once())->method('append')->with([$expectedMessage], null);

        $appender = new StoreEventAppender($store);

        $appender->append([$expectedEvent]);
    }

    public function testAppendEventsWithCondition(): void
    {
        $appendCondition = new AppendCondition(new Query(), 5);

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $message = (new Message($event))
            ->withHeader(new TagsHeader([]))
            ->withHeader(new StreamNameHeader('main'));

        $store = $this->createMock(AppendStore::class);
        $store->expects($this->once())->method('append')->with([$message], $appendCondition);

        $appender = new StoreEventAppender($store);

        $appender->append([$event], $appendCondition);
    }

    public function testChangeDefaultStreamName(): void
    {
        $appendCondition = new AppendCondition(new Query(), 5);

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $message = (new Message($event))
            ->withHeader(new TagsHeader([]))
            ->withHeader(new StreamNameHeader('foo'));

        $store = $this->createMock(AppendStore::class);
        $store->expects($this->once())->method('append')->with([$message], $appendCondition);

        $appender = new StoreEventAppender($store, defaultStreamName: 'foo');

        $appender->append([$event], $appendCondition);
    }

    public function testChangeStreamName(): void
    {
        $appendCondition = new AppendCondition(new Query(), 5);

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $message = (new Message($event))
            ->withHeader(new TagsHeader([]))
            ->withHeader(new StreamNameHeader('bar'));

        $store = $this->createMock(AppendStore::class);
        $store->expects($this->once())->method('append')->with([$message], $appendCondition);

        $appender = new StoreEventAppender($store, defaultStreamName: 'foo');

        $appender->append([$event], $appendCondition, 'bar');
    }

    public function testExtractTags(): void
    {
        $appendCondition = new AppendCondition(new Query(), 5);

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $message = (new Message($event))
            ->withHeader(new TagsHeader(['profile:1']))
            ->withHeader(new StreamNameHeader('main'));

        $store = $this->createMock(AppendStore::class);
        $store->expects($this->once())->method('append')->with([$message], $appendCondition);

        $tagExtractor = $this->createMock(EventTagExtractor::class);
        $tagExtractor->expects($this->once())->method('extract')->with($event)->willReturn(['profile:1']);

        $appender = new StoreEventAppender($store, $tagExtractor);

        $appender->append([$event], $appendCondition);
    }
}
