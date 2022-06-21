<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Encoder;

interface Encoder
{
    public const OPTION_PRETTY_PRINT = 'pretty_print';

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function encode(array $data, array $options = []): string;

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function decode(string $data, array $options = []): array;
}
