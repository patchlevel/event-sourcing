<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\EventBus\ListenerDescriptor;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DefaultConsumer::class)]
final class DefaultConsumerTest extends TestCase
{
    public function testConsumeEvent(): void
    {
        $listener = new class {
            public Message|null $message = null;

            public function __invoke(Message $message): void
            {
                $this->message = $message;
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $provider = $this->createMock(ListenerProvider::class);
        $provider
            ->expects($this->once())
            ->method('listenersForEvent')
            ->with(ProfileCreated::class)
            ->willReturn([new ListenerDescriptor($listener->__invoke(...))]);

        $eventBus = new DefaultConsumer($provider);
        $eventBus->consume($message);

        self::assertSame($message, $listener->message);
    }

    public function testConsumeWithSubscribe(): void
    {
        $listener = new class {
            public Message|null $message = null;

            #[Subscribe(ProfileCreated::class)]
            public function __invoke(Message $message): void
            {
                $this->message = $message;
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $eventBus = DefaultConsumer::create([$listener]);
        $eventBus->consume($message);

        self::assertSame($message, $listener->message);
    }

    public function testPushMessageContextWhileConsuming(): void
    {
        $context = new MessageContext();

        $listener = new class ($context) {
            public string|null $causationId = null;
            public string|null $correlationId = null;

            public function __construct(private readonly MessageContext $context)
            {
            }

            public function __invoke(Message $message): void
            {
                $this->causationId = $this->context->causationId();
                $this->correlationId = $this->context->correlationId();
            }
        };

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        )
            ->withHeader(new EventIdHeader('event-1'))
            ->withHeader(new CorrelationIdHeader('correlation-1'));

        $provider = $this->createMock(ListenerProvider::class);
        $provider
            ->expects($this->once())
            ->method('listenersForEvent')
            ->with(ProfileCreated::class)
            ->willReturn([new ListenerDescriptor($listener->__invoke(...))]);

        $consumer = new DefaultConsumer($provider, null, $context);
        $consumer->consume($message);

        self::assertSame('event-1', $listener->causationId);
        self::assertSame('correlation-1', $listener->correlationId);
        self::assertNull($context->causationId());
        self::assertNull($context->correlationId());
    }

    public function testPopMessageContextOnException(): void
    {
        $context = new MessageContext();

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        )->withHeader(new EventIdHeader('event-1'));

        $provider = $this->createMock(ListenerProvider::class);
        $provider
            ->expects($this->once())
            ->method('listenersForEvent')
            ->with(ProfileCreated::class)
            ->willReturn([
                new ListenerDescriptor(
                    static fn (Message $message) => throw new RuntimeException('ERROR'),
                ),
            ]);

        $consumer = new DefaultConsumer($provider, null, $context);

        try {
            $consumer->consume($message);
        } catch (RuntimeException) {
            // expected
        }

        self::assertNull($context->causationId());
    }
}
