<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Worker;

use function array_key_exists;
use function preg_match;
use function strtoupper;

final class Bytes
{
    private const SIZES = [
        'B' => 1,
        'KB' => 1_024,
        'MB' => 1_048_576,
        'GB' => 1_073_741_824,
    ];

    public function __construct(
        private readonly int $bytes,
    ) {
    }

    public function value(): int
    {
        return $this->bytes;
    }

    public static function parseFromString(string $string): self
    {
        if (!preg_match('/^([0-9]+)([a-z]+)?$/i', $string, $matches)) {
            throw new InvalidFormat($string);
        }

        $number = (int)$matches[1];
        $unit = strtoupper($matches[2] ?? 'B');

        if (!array_key_exists($unit, self::SIZES)) {
            throw new InvalidFormat($string);
        }

        return new self($number * self::SIZES[$unit]);
    }
}
