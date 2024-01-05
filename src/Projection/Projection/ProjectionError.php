<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\Store\ErrorContext;
use Throwable;

/** @psalm-import-type Context from ErrorContext */
final class ProjectionError
{
    /** @param list<Context>|null $errorContext */
    public function __construct(
        public readonly string $errorMessage,
        public readonly array|null $errorContext = null,
    ) {
    }

    public static function fromThrowable(Throwable $error): self
    {
        return new self($error->getMessage(), ErrorContext::fromThrowable($error));
    }
}
