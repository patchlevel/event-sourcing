<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use function sprintf;

/**
 * @psalm-immutable
 */
final class ProjectorId
{
    public function __construct(
        private readonly string $name,
        private readonly int $version
    ) {
    }

    public function toString(): string
    {
        return sprintf('%s-%s', $this->name, $this->version);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name && $this->version === $other->version;
    }
}
