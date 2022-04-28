<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Encoder;

use Patchlevel\EventSourcing\Serializer\SerializeException;
use Throwable;

use function sprintf;

final class DecodeNotPossible extends SerializeException
{
    private string $data;

    public function __construct(string $data, ?Throwable $previous = null)
    {
        $this->data = $data;

        parent::__construct(
            sprintf(
                'deserialization of "%s" data is not possible',
                $data
            ),
            0,
            $previous
        );
    }

    public function data(): string
    {
        return $this->data;
    }
}
