<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Identifier\FakeRamseyUuidFactory;
use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\CorrelationCausationDecorator;
use Patchlevel\EventSourcing\Store\Header\CausationIdHeader;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidFactoryInterface;

#[CoversClass(CorrelationCausationDecorator::class)]
final class CorrelationCausationDecoratorTest extends TestCase
{
    private UuidFactoryInterface $previousUuidFactory;

    protected function setUp(): void
    {
        $this->previousUuidFactory = RamseyUuid::getFactory();

        RamseyUuid::setFactory(new FakeRamseyUuidFactory());
    }

    protected function tearDown(): void
    {
        RamseyUuid::setFactory($this->previousUuidFactory);
    }

    public function testRootMessageCorrelatesToItself(): void
    {
        $decorator = new CorrelationCausationDecorator(new MessageContext());

        $message = $decorator(Message::create($this->event()));

        $eventId = $message->header(EventIdHeader::class)->eventId;

        self::assertSame('10000000-7000-0000-0000-000000000001', $eventId);
        self::assertSame($eventId, $message->header(CorrelationIdHeader::class)->correlationId);
        self::assertFalse($message->hasHeader(CausationIdHeader::class));
    }

    public function testInheritsIdsFromContext(): void
    {
        $context = new MessageContext();
        $context->push('causation-1', 'correlation-1');

        $decorator = new CorrelationCausationDecorator($context);

        $message = $decorator(Message::create($this->event()));

        self::assertSame('10000000-7000-0000-0000-000000000001', $message->header(EventIdHeader::class)->eventId);
        self::assertSame('causation-1', $message->header(CausationIdHeader::class)->causationId);
        self::assertSame('correlation-1', $message->header(CorrelationIdHeader::class)->correlationId);
    }

    public function testCorrelatesToItselfWhenContextHasOnlyCausationId(): void
    {
        $context = new MessageContext();
        $context->push('causation-1');

        $decorator = new CorrelationCausationDecorator($context);

        $message = $decorator(Message::create($this->event()));

        $eventId = $message->header(EventIdHeader::class)->eventId;

        self::assertSame('causation-1', $message->header(CausationIdHeader::class)->causationId);
        self::assertSame($eventId, $message->header(CorrelationIdHeader::class)->correlationId);
    }

    public function testKeepsExistingEventId(): void
    {
        $decorator = new CorrelationCausationDecorator(new MessageContext());

        $message = $decorator(
            Message::create($this->event())->withHeader(new EventIdHeader('event-1')),
        );

        self::assertSame('event-1', $message->header(EventIdHeader::class)->eventId);
        self::assertSame('event-1', $message->header(CorrelationIdHeader::class)->correlationId);
    }

    public function testDoesNotOverwriteExplicitHeaders(): void
    {
        $context = new MessageContext();
        $context->push('causation-1', 'correlation-1');

        $decorator = new CorrelationCausationDecorator($context);

        $message = $decorator(
            Message::create($this->event())
                ->withHeader(new CausationIdHeader('explicit-causation'))
                ->withHeader(new CorrelationIdHeader('explicit-correlation')),
        );

        self::assertSame('explicit-causation', $message->header(CausationIdHeader::class)->causationId);
        self::assertSame('explicit-correlation', $message->header(CorrelationIdHeader::class)->correlationId);
    }

    public function testEveryMessageOfOneContextSharesCorrelationId(): void
    {
        $context = new MessageContext();
        $context->push(null, 'correlation-1');

        $decorator = new CorrelationCausationDecorator($context);

        $first = $decorator(Message::create($this->event()));
        $second = $decorator(Message::create($this->event()));

        self::assertNotSame(
            $first->header(EventIdHeader::class)->eventId,
            $second->header(EventIdHeader::class)->eventId,
        );
        self::assertSame('correlation-1', $first->header(CorrelationIdHeader::class)->correlationId);
        self::assertSame('correlation-1', $second->header(CorrelationIdHeader::class)->correlationId);
    }

    private function event(): ProfileCreated
    {
        return new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );
    }
}
