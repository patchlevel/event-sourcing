<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use Throwable;

final class Projection
{
    public function __construct(
        private readonly ProjectionId $id,
        private ProjectionStatus $status = ProjectionStatus::New,
        private int $position = 0,
        private string|null $errorMessage = null,
        private Throwable|null $errorObject = null,
    ) {
    }

    public function id(): ProjectionId
    {
        return $this->id;
    }

    public function status(): ProjectionStatus
    {
        return $this->status;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function errorMessage(): string|null
    {
        return $this->errorMessage;
    }

    public function errorObject(): Throwable|null
    {
        return $this->errorObject;
    }

    public function incrementPosition(): void
    {
        $this->position++;
    }

    public function isNew(): bool
    {
        return $this->status === ProjectionStatus::New;
    }

    public function booting(): void
    {
        $this->status = ProjectionStatus::Booting;
        $this->errorMessage = null;
        $this->errorObject = null;
    }

    public function isBooting(): bool
    {
        return $this->status === ProjectionStatus::Booting;
    }

    public function active(): void
    {
        $this->status = ProjectionStatus::Active;
        $this->errorMessage = null;
        $this->errorObject = null;
    }

    public function isActive(): bool
    {
        return $this->status === ProjectionStatus::Active;
    }

    public function outdated(): void
    {
        $this->status = ProjectionStatus::Outdated;
    }

    public function isOutdated(): bool
    {
        return $this->status === ProjectionStatus::Outdated;
    }

    public function error(Throwable|string|null $error = null): void
    {
        $this->status = ProjectionStatus::Error;
        $this->errorMessage = $error instanceof Throwable ? $error->getMessage() : $error;
        $this->errorObject = $error instanceof Throwable ? $error : null;
    }

    public function isError(): bool
    {
        return $this->status === ProjectionStatus::Error;
    }
}
