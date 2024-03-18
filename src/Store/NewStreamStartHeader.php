<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Message\Header;

/**
 * @template-implements Header<array{newStreamStart: bool}>
 * @psalm-immutable
 */
final class NewStreamStartHeader implements Header
{
    public function __construct(
        public readonly bool $newStreamStart,
    ) {
    }

    public static function name(): string
    {
        return 'newStreamStart';
    }

    public static function fromJsonSerialize(array $data): static
    {
        return new self($data['newStreamStart']);
    }

    public function jsonSerialize(): array
    {
        return [
            'newStreamStart' => $this->newStreamStart,
        ];
    }
}
