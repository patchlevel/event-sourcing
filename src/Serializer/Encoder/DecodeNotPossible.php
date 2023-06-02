<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Encoder;

use Patchlevel\EventSourcing\Serializer\SerializeException;
use Throwable;

use function sprintf;

final class DecodeNotPossible extends SerializeException
{
    public function __construct(
        private string $data,
        Throwable|null $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'deserialization of "%s" data is not possible',
                $data,
            ),
            0,
            $previous,
        );
    }

    public function data(): string
    {
        return $this->data;
    }
}
