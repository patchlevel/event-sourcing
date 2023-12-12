<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Encoder;

use Patchlevel\EventSourcing\Serializer\SerializeException;
use Throwable;

final class EncodeNotPossible extends SerializeException
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data, Throwable|null $previous = null)
    {
        parent::__construct(
            'serialization is not possible',
            0,
            $previous,
        );
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }
}
