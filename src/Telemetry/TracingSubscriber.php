<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\TraceHeader;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function array_filter;

/**
 * Creates one consumer span per message and subscription.
 *
 * The span of the trace which originally recorded the message is attached as a link,
 * not as a parent, because a single message is processed by many subscriptions at
 * arbitrary points in time.
 */
final class TracingSubscriber implements EventSubscriberInterface
{
    public const SPAN_NAME = 'event_sourcing.subscription.process';

    private readonly TracerInterface $tracer;
    private readonly TextMapPropagatorInterface $propagator;

    private SpanInterface|null $span = null;
    private ScopeInterface|null $scope = null;

    public function __construct(
        TracerProviderInterface|null $tracerProvider = null,
        TextMapPropagatorInterface|null $propagator = null,
    ) {
        $this->tracer = ($tracerProvider ?? Globals::tracerProvider())->getTracer(Instrumentation::SCOPE);
        $this->propagator = $propagator ?? TraceContextPropagator::getInstance();
    }

    public function onCommand(OnCommand $event): void
    {
        // a span may have leaked if a listener threw before the message was finished
        $this->closeSpan();
    }

    public function onHandleMessage(OnHandleMessage $event): void
    {
        $this->closeSpan();

        $spanBuilder = $this->tracer
            ->spanBuilder(self::SPAN_NAME)
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->setAttributes(array_filter(
                [
                    TraceAttributes::MESSAGING_SYSTEM => TraceAttributes::SYSTEM,
                    TraceAttributes::MESSAGING_OPERATION_NAME => 'process',
                    TraceAttributes::MESSAGING_OPERATION_TYPE => TraceAttributes::OPERATION_TYPE_PROCESS,
                    TraceAttributes::SUBSCRIPTION_ID => $event->subscription->id(),
                    TraceAttributes::SUBSCRIPTION_GROUP => $event->subscription->group(),
                    ...MessageAttributes::from($event->message),
                ],
                static fn (mixed $value) => $value !== null,
            ));

        $link = $this->linkFor($event->message);

        if ($link instanceof SpanContextInterface && $link->isValid()) {
            $spanBuilder = $spanBuilder->addLink($link);
        }

        $this->span = $spanBuilder->startSpan();
        $this->scope = $this->span->activate();
    }

    public function onHandleMessageSuccess(OnHandleMessageSuccess $event): void
    {
        $this->span?->setStatus(StatusCode::STATUS_OK);
        $this->closeSpan();
    }

    public function onHandleMessageError(OnHandleMessageError $event): void
    {
        $this->span?->recordException($event->throwable);
        $this->span?->setStatus(StatusCode::STATUS_ERROR, $event->throwable->getMessage());
        $this->closeSpan();
    }

    private function linkFor(Message $message): SpanContextInterface|null
    {
        if (!$message->hasHeader(TraceHeader::class)) {
            return null;
        }

        $header = $message->header(TraceHeader::class);

        $carrier = [TraceContextPropagator::TRACEPARENT => $header->traceparent];

        if ($header->tracestate !== null) {
            $carrier[TraceContextPropagator::TRACESTATE] = $header->tracestate;
        }

        // extract against the root context: the propagator falls back to the given context
        // for an invalid traceparent, and the current context would then link the span to
        // its own parent
        return Span::fromContext($this->propagator->extract($carrier, null, Context::getRoot()))->getContext();
    }

    private function closeSpan(): void
    {
        $this->scope?->detach();
        $this->span?->end();

        $this->scope = null;
        $this->span = null;
    }

    /** @return array<class-string, string|array{string, int}> */
    public static function getSubscribedEvents(): array
    {
        // the span is closed after every other listener reacted to the terminal event, so
        // that work they do is measured and a listener which fails marks the span as
        // failed. MessageProcessor guarantees a terminal event, so the span cannot leak.
        return [
            OnCommand::class => ['onCommand', 32],
            OnHandleMessage::class => ['onHandleMessage', 32],
            OnHandleMessageSuccess::class => ['onHandleMessageSuccess', -256],
            OnHandleMessageError::class => ['onHandleMessageError', -256],
        ];
    }
}
