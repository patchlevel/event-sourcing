<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Header;

use Patchlevel\EventSourcing\Message\Header;

/**
 * @template-implements Header<array{data: string}>
 * @psalm-immutable
 */
final class FooHeader implements Header
{
    public function __construct(
        public readonly string $data,
    ) {
    }

    public static function name(): string
    {
        return 'foo';
    }

    public static function fromJsonSerialize(array $data): static
    {
        return new self($data['data']);
    }

    public function jsonSerialize(): array
    {
        return [
            'data' => $this->data,
        ];
    }
}
