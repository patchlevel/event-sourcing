<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use Throwable;

/**
 * @psalm-type Trace = array{file?: string, line?: int, function?: string, class?: string, type?: string, args?: array<array-key, mixed>}
 * @psalm-type Context = array{class: class-string, message: string, code: int|string, file: string, line: int, trace: list<Trace>}
 */
final class ProjectionError
{
    /** @param list<Context>|null $errorContext */
    public function __construct(
        public readonly string $errorMessage,
        public readonly ProjectionStatus $previousStatus,
        public readonly array|null $errorContext = null,
    ) {
    }

    public static function fromThrowable(ProjectionStatus $projectionStatus, Throwable $error): self
    {
        return new self($error->getMessage(), $projectionStatus, ThrowableToErrorContextTransformer::transform($error));
    }
}
