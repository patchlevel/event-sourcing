<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug\Trace;

use Patchlevel\EventSourcing\Message\Header;

/**
 * @experimental
 * @template-implements Header<array{traces: list<array{name: string, category: string}>}>
 * @psalm-immutable
 */
final class TraceHeader implements Header
{
    /** @param list<array{name: string, category: string}> $traces */
    public function __construct(
        public readonly array $traces,
    ) {
    }

    public static function name(): string
    {
        return 'trace';
    }

    public static function fromJsonSerialize(array $data): static
    {
        return new self($data['traces']);
    }

    public function jsonSerialize(): array
    {
        return [
            'traces' => $this->traces,
        ];
    }
}
