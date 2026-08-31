<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Context;

use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageContext::class)]
final class MessageContextTest extends TestCase
{
    public function testEmptyContext(): void
    {
        $context = new MessageContext();

        self::assertNull($context->causationId());
        self::assertNull($context->correlationId());
    }

    public function testPush(): void
    {
        $context = new MessageContext();
        $context->push('causation-1', 'correlation-1');

        self::assertSame('causation-1', $context->causationId());
        self::assertSame('correlation-1', $context->correlationId());
    }

    public function testNestedPushAndPop(): void
    {
        $context = new MessageContext();
        $context->push('causation-1', 'correlation-1');
        $context->push('causation-2', 'correlation-2');

        self::assertSame('causation-2', $context->causationId());
        self::assertSame('correlation-2', $context->correlationId());

        $context->pop();

        self::assertSame('causation-1', $context->causationId());
        self::assertSame('correlation-1', $context->correlationId());

        $context->pop();

        self::assertNull($context->causationId());
        self::assertNull($context->correlationId());
    }

    public function testPopOnEmptyContext(): void
    {
        $context = new MessageContext();
        $context->pop();

        self::assertNull($context->causationId());
        self::assertNull($context->correlationId());
    }

    public function testClear(): void
    {
        $context = new MessageContext();
        $context->push('causation-1', 'correlation-1');
        $context->push('causation-2', 'correlation-2');

        $context->clear();

        self::assertNull($context->causationId());
        self::assertNull($context->correlationId());
    }

    public function testPushMessage(): void
    {
        $message = Message::create($this->event())
            ->withHeader(new EventIdHeader('event-1'))
            ->withHeader(new CorrelationIdHeader('correlation-1'));

        $context = new MessageContext();
        $context->pushMessage($message);

        self::assertSame('event-1', $context->causationId());
        self::assertSame('correlation-1', $context->correlationId());
    }

    public function testPushMessageWithoutCorrelationIdIsRootOfCorrelation(): void
    {
        $message = Message::create($this->event())
            ->withHeader(new EventIdHeader('event-1'));

        $context = new MessageContext();
        $context->pushMessage($message);

        self::assertSame('event-1', $context->causationId());
        self::assertSame('event-1', $context->correlationId());
    }

    public function testPushMessageWithoutHeaders(): void
    {
        $context = new MessageContext();
        $context->push('causation-1', 'correlation-1');
        $context->pushMessage(Message::create($this->event()));

        self::assertNull($context->causationId());
        self::assertNull($context->correlationId());

        $context->pop();

        self::assertSame('causation-1', $context->causationId());
        self::assertSame('correlation-1', $context->correlationId());
    }

    private function event(): ProfileCreated
    {
        return new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );
    }
}
