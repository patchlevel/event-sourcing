<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use InvalidArgumentException;

use function array_pop;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function sprintf;

/**
 * @psalm-immutable
 */
final class ProjectorId
{
    public function __construct(
        private readonly string $name,
        private readonly int $version = 1
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

    public static function fromString(string $value): self
    {
        $parts = explode('-', $value);

        if (count($parts) < 2) {
            throw new InvalidArgumentException();
        }

        $version = array_pop($parts);

        if (!ctype_digit($version)) {
            throw new InvalidArgumentException();
        }

        $name = implode('-', $parts);

        return new self($name, (int)$version);
    }
}
