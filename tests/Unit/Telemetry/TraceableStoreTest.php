<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use OpenTelemetry\API\Trace\SpanKind;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Telemetry\StoreDoesNotSupport;
use Patchlevel\EventSourcing\Telemetry\TraceableStore;
use Patchlevel\EventSourcing\Telemetry\TraceAttributes;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TraceableStore::class)]
final class TraceableStoreTest extends TestCase
{
    use InMemoryTracer;

    public function testLoad(): void
    {
        $stream = new Stream();

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(null, null, null, false)
            ->willReturn($stream);

        $traceableStore = new TraceableStore($store, $this->createTracerProvider());

        self::assertSame($stream, $traceableStore->load());
        self::assertSame('event_sourcing.store.load', $this->span()->getName());
    }

    public function testCount(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('count')
            ->with(null)
            ->willReturn(3);

        $traceableStore = new TraceableStore($store, $this->createTracerProvider());

        self::assertSame(3, $traceableStore->count());
        self::assertSame('event_sourcing.store.count', $this->span()->getName());
    }

    public function testSave(): void
    {
        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('save')
            ->with($message);

        $traceableStore = new TraceableStore($store, $this->createTracerProvider());
        $traceableStore->save($message);

        $span = $this->span();

        self::assertSame('event_sourcing.store.save', $span->getName());
        self::assertSame(SpanKind::KIND_PRODUCER, $span->getKind());
        self::assertSame(1, $span->getAttributes()->get(TraceAttributes::MESSAGING_BATCH_MESSAGE_COUNT));
        self::assertSame(
            TraceAttributes::SYSTEM,
            $span->getAttributes()->get(TraceAttributes::MESSAGING_SYSTEM),
        );
    }

    public function testTransactional(): void
    {
        $function = static fn (): null => null;

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('transactional')
            ->with($function);

        $traceableStore = new TraceableStore($store, $this->createTracerProvider());
        $traceableStore->transactional($function);

        self::assertSame('event_sourcing.store.transactional', $this->span()->getName());
    }

    public function testStreams(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('streams')
            ->willReturn(['profile-1']);

        $traceableStore = new TraceableStore($store, $this->createTracerProvider());

        self::assertSame(['profile-1'], $traceableStore->streams());
        self::assertSame('event_sourcing.store.streams', $this->span()->getName());
    }

    public function testRemove(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('remove')
            ->with(null);

        $traceableStore = new TraceableStore($store, $this->createTracerProvider());
        $traceableStore->remove();

        self::assertSame('event_sourcing.store.remove', $this->span()->getName());
    }

    public function testArchive(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('archive')
            ->with(null);

        $traceableStore = new TraceableStore($store, $this->createTracerProvider());
        $traceableStore->archive();

        self::assertSame('event_sourcing.store.archive', $this->span()->getName());
    }

    public function testAppend(): void
    {
        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));
        $appendCondition = new AppendCondition();

        $store = $this->createMockForIntersectionOfInterfaces([Store::class, AppendStore::class]);
        $store
            ->expects($this->once())
            ->method('append')
            ->with([$message], $appendCondition);

        $traceableStore = new TraceableStore($store, $this->createTracerProvider());
        $traceableStore->append((static fn () => yield $message)(), $appendCondition);

        $span = $this->span();

        self::assertSame('event_sourcing.store.append', $span->getName());
        self::assertSame(SpanKind::KIND_PRODUCER, $span->getKind());
        self::assertSame(1, $span->getAttributes()->get(TraceAttributes::MESSAGING_BATCH_MESSAGE_COUNT));
    }

    public function testAppendNotSupported(): void
    {
        $store = $this->createMock(Store::class);
        $traceableStore = new TraceableStore($store, $this->createTracerProvider());

        $this->expectException(StoreDoesNotSupport::class);
        $this->expectExceptionMessage(AppendStore::class);

        $traceableStore->append([]);
    }

    public function testQuery(): void
    {
        $stream = new Stream();
        $query = new Query();

        $store = $this->createMockForIntersectionOfInterfaces([Store::class, AppendStore::class]);
        $store
            ->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn($stream);

        $traceableStore = new TraceableStore($store, $this->createTracerProvider());

        self::assertSame($stream, $traceableStore->query($query));
        self::assertSame('event_sourcing.store.query', $this->span()->getName());
    }

    public function testQueryNotSupported(): void
    {
        $store = $this->createMock(Store::class);
        $traceableStore = new TraceableStore($store, $this->createTracerProvider());

        $this->expectException(StoreDoesNotSupport::class);

        $traceableStore->query(new Query());
    }
}
