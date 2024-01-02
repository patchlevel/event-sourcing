<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use Throwable;

final class ProjectionError
{
    public function __construct(
        public readonly string $errorMessage,
        public readonly Throwable|null $errorObject = null,
    ) {
    }

    public static function fromThrowable(Throwable $error): self
    {
        return new self($error->getMessage(), $error);
    }
}
