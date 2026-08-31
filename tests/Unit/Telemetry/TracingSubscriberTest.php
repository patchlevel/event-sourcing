<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\CausationIdHeader;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\TraceHeader;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Telemetry\Instrumentation;
use Patchlevel\EventSourcing\Telemetry\TraceAttributes;
use Patchlevel\EventSourcing\Telemetry\TraceDecorator;
use Patchlevel\EventSourcing\Telemetry\TracingSubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_keys;

#[CoversClass(TracingSubscriber::class)]
final class TracingSubscriberTest extends TestCase
{
    use InMemoryTracer;

    public function testCreatesConsumerSpanPerMessage(): void
    {
        $listener = new TracingSubscriber($this->createTracerProvider());

        $subscription = new Subscription('profile_projection', 'reporting');
        $message = $this->message();

        $listener->onHandleMessage(new OnHandleMessage($subscription, $message));
        $listener->onHandleMessageSuccess(new OnHandleMessageSuccess($subscription, $message, 1));

        self::assertCount(1, $this->spans());

        $span = $this->span();
        $attributes = $span->getAttributes();

        self::assertSame(TracingSubscriber::SPAN_NAME, $span->getName());
        self::assertSame(SpanKind::KIND_CONSUMER, $span->getKind());
        self::assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());
        self::assertSame('profile_projection', $attributes->get(TraceAttributes::SUBSCRIPTION_ID));
        self::assertSame('reporting', $attributes->get(TraceAttributes::SUBSCRIPTION_GROUP));
        self::assertSame(ProfileVisited::class, $attributes->get(TraceAttributes::EVENT_NAME));
        self::assertSame('event-1', $attributes->get(TraceAttributes::MESSAGING_MESSAGE_ID));
        self::assertSame(
            'correlation-1',
            $attributes->get(TraceAttributes::MESSAGING_MESSAGE_CONVERSATION_ID),
        );
        self::assertSame('causation-1', $attributes->get(TraceAttributes::CAUSATION_ID));
    }

    public function testLinksToTheTraceWhichRecordedTheMessage(): void
    {
        $tracerProvider = $this->createTracerProvider();
        $instrumentation = new Instrumentation($tracerProvider);
        $decorator = new TraceDecorator();

        // the message is recorded inside the producing trace
        $message = $instrumentation->span(
            'producer',
            fn (): Message => $decorator($this->message()),
        );

        $listener = new TracingSubscriber($tracerProvider);

        $subscription = new Subscription('profile_projection');
        $listener->onHandleMessage(new OnHandleMessage($subscription, $message));
        $listener->onHandleMessageSuccess(new OnHandleMessageSuccess($subscription, $message, 1));

        [$producerSpan, $consumerSpan] = $this->spans();

        $links = $consumerSpan->getLinks();

        self::assertCount(1, $links);
        self::assertSame(
            $producerSpan->getContext()->getTraceId(),
            $links[0]->getSpanContext()->getTraceId(),
        );
        self::assertSame(
            $producerSpan->getContext()->getSpanId(),
            $links[0]->getSpanContext()->getSpanId(),
        );

        // a link, not a parent: the consumer span starts its own trace
        self::assertNotSame(
            $producerSpan->getContext()->getTraceId(),
            $consumerSpan->getContext()->getTraceId(),
        );
    }

    public function testNoLinkWithoutTraceHeader(): void
    {
        $listener = new TracingSubscriber($this->createTracerProvider());

        $subscription = new Subscription('profile_projection');
        $message = $this->message();

        $listener->onHandleMessage(new OnHandleMessage($subscription, $message));
        $listener->onHandleMessageSuccess(new OnHandleMessageSuccess($subscription, $message, 1));

        self::assertCount(0, $this->span()->getLinks());
    }

    public function testInvalidTraceHeaderIsIgnored(): void
    {
        $listener = new TracingSubscriber($this->createTracerProvider());

        $subscription = new Subscription('profile_projection');
        $message = $this->message()->withHeader(new TraceHeader('not-a-traceparent'));

        $listener->onHandleMessage(new OnHandleMessage($subscription, $message));
        $listener->onHandleMessageSuccess(new OnHandleMessageSuccess($subscription, $message, 1));

        self::assertCount(0, $this->span()->getLinks());
    }

    public function testInvalidTraceHeaderDoesNotLinkToTheActiveSpan(): void
    {
        $tracerProvider = $this->createTracerProvider();
        $listener = new TracingSubscriber($tracerProvider);

        $subscription = new Subscription('profile_projection');
        $message = $this->message()->withHeader(new TraceHeader('not-a-traceparent'));

        // the engine run span is active while the message is handled
        (new Instrumentation($tracerProvider))->span(
            'event_sourcing.subscription.run',
            static function () use ($listener, $subscription, $message): void {
                $listener->onHandleMessage(new OnHandleMessage($subscription, $message));
                $listener->onHandleMessageSuccess(new OnHandleMessageSuccess($subscription, $message, 1));
            },
        );

        [$consumerSpan, $runSpan] = $this->spans();

        self::assertSame(TracingSubscriber::SPAN_NAME, $consumerSpan->getName());
        self::assertSame($runSpan->getContext()->getSpanId(), $consumerSpan->getParentContext()->getSpanId());
        self::assertCount(0, $consumerSpan->getLinks());
    }

    public function testRecordsError(): void
    {
        $listener = new TracingSubscriber($this->createTracerProvider());

        $subscription = new Subscription('profile_projection');
        $message = $this->message();

        $listener->onHandleMessage(new OnHandleMessage($subscription, $message));
        $listener->onHandleMessageError(new OnHandleMessageError(
            $subscription,
            new RuntimeException('ERROR'),
            $message,
            1,
        ));

        $span = $this->span();

        self::assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        self::assertSame('ERROR', $span->getStatus()->getDescription());
        self::assertCount(1, $span->getEvents());
    }

    public function testSuccessWithoutHandleMessageCreatesNoSpan(): void
    {
        $listener = new TracingSubscriber($this->createTracerProvider());

        // a subscriber without a matching subscribe method is reported as
        // success without a preceding OnHandleMessage
        $listener->onHandleMessageSuccess(new OnHandleMessageSuccess(
            new Subscription('profile_projection'),
            $this->message(),
            1,
        ));

        self::assertCount(0, $this->spans());
    }

    public function testSpanIsNotLeakedWhenTerminalListenerFails(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new TracingSubscriber($this->createTracerProvider()));

        // a batch flush runs on the terminal event and can fail
        $eventDispatcher->addListener(
            OnHandleMessageSuccess::class,
            static fn (): never => throw new RuntimeException('FLUSH FAILED'),
        );

        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event): void
            {
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            $eventDispatcher,
            [],
            new NullLogger(),
        );

        $error = $processor->process(1, $this->message(), new Subscription('test'));

        self::assertNotNull($error);

        // the span must be ended and exported, and must not stay active as the parent
        // of everything the application does afterwards
        self::assertCount(1, $this->spans());
        self::assertFalse(Span::getCurrent()->getContext()->isValid());

        // and it must report the failure: a message which ended in an error must not
        // leave an Ok span behind
        $span = $this->span();

        self::assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        self::assertSame('FLUSH FAILED', $span->getStatus()->getDescription());
        self::assertCount(1, $span->getEvents());
        self::assertSame('exception', $span->getEvents()[0]->getName());
    }

    public function testSpanMeasuresWorkOfOtherTerminalListeners(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new TracingSubscriber($this->createTracerProvider()));

        $spanDuringFlush = null;

        // a batch flush runs on the terminal event, its work belongs inside the span
        $eventDispatcher->addListener(
            OnHandleMessageSuccess::class,
            static function () use (&$spanDuringFlush): void {
                $spanDuringFlush = Span::getCurrent()->getContext()->getSpanId();
            },
        );

        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event): void
            {
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            $eventDispatcher,
            [],
            new NullLogger(),
        );

        $processor->process(1, $this->message(), new Subscription('test'));

        self::assertSame($this->span()->getContext()->getSpanId(), $spanDuringFlush);
    }

    public function testCommandClosesLeakedSpan(): void
    {
        $listener = new TracingSubscriber($this->createTracerProvider());

        $listener->onHandleMessage(new OnHandleMessage(
            new Subscription('profile_projection'),
            $this->message(),
        ));

        self::assertCount(0, $this->spans());

        $listener->onCommand(new OnCommand(new Run()));

        self::assertCount(1, $this->spans());
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame(
            [
                OnCommand::class,
                OnHandleMessage::class,
                OnHandleMessageSuccess::class,
                OnHandleMessageError::class,
            ],
            array_keys(TracingSubscriber::getSubscribedEvents()),
        );
    }

    private function message(): Message
    {
        return Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new EventIdHeader('event-1'))
            ->withHeader(new CorrelationIdHeader('correlation-1'))
            ->withHeader(new CausationIdHeader('causation-1'));
    }
}
