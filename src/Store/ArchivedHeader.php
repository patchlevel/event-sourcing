<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Message\Header;

/**
 * @template-implements Header<array{archived: bool}>
 * @psalm-immutable
 */
final class ArchivedHeader implements Header
{
    public function __construct(
        public readonly bool $archived,
    ) {
    }

    public static function name(): string
    {
        return 'archived';
    }

    public static function fromJsonSerialize(array $data): static
    {
        return new self($data['archived']);
    }

    public function jsonSerialize(): array
    {
        return [
            'archived' => $this->archived,
        ];
    }
}
