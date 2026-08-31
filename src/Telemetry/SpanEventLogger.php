<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Trace\Span;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

use function is_scalar;

/**
 * Adds every log record to the currently active span as a span event.
 *
 * The library logs at all of its seams, so this covers the parts which are not
 * wrapped by a dedicated span. The log context is deliberately not attached,
 * because it can contain personal data.
 */
final class SpanEventLogger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $span = Span::getCurrent();

        if ($span->isRecording()) {
            $span->addEvent((string)$message, [
                'log.level' => is_scalar($level) ? (string)$level : 'unknown',
            ]);
        }

        $this->logger?->log($level, $message, $context);
    }
}
