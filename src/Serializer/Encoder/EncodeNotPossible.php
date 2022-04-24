<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Encoder;

use Patchlevel\EventSourcing\Serializer\SerializeException;
use Throwable;

use function sprintf;

final class EncodeNotPossible extends SerializeException
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'serialization is not possible',
            ),
            0,
            $previous
        );

        $this->data = $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
