<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use Closure;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Throwable;

use function array_filter;

/**
 * Creates the spans for all traceable wrappers.
 *
 * Without a configured SDK the tracer provider is a noop implementation,
 * so wrapping is always safe.
 *
 * @internal
 */
final class Instrumentation
{
    public const SCOPE = 'patchlevel/event-sourcing';

    private readonly TracerInterface $tracer;

    public function __construct(TracerProviderInterface|null $tracerProvider = null)
    {
        $this->tracer = ($tracerProvider ?? Globals::tracerProvider())->getTracer(self::SCOPE);
    }

    /**
     * @param non-empty-string                          $name
     * @param Closure():T                               $function
     * @param 0|1|2|3|4                                 $kind
     * @param array<string, bool|float|int|string|null> $attributes
     *
     * @return T
     *
     * @template T
     */
    public function span(
        string $name,
        Closure $function,
        int $kind = SpanKind::KIND_INTERNAL,
        array $attributes = [],
        SpanContextInterface|null $link = null,
    ): mixed {
        $spanBuilder = $this->tracer
            ->spanBuilder($name)
            ->setSpanKind($kind)
            ->setAttributes(array_filter($attributes, static fn (mixed $value) => $value !== null));

        if ($link instanceof SpanContextInterface && $link->isValid()) {
            $spanBuilder = $spanBuilder->addLink($link);
        }

        $span = $spanBuilder->startSpan();
        $scope = $span->activate();

        try {
            return $function();
        } catch (Throwable $throwable) {
            $span->recordException($throwable);
            $span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());

            throw $throwable;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
