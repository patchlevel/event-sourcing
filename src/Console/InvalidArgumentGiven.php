<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use InvalidArgumentException;

use function gettype;
use function sprintf;

final class InvalidArgumentGiven extends InvalidArgumentException
{
    private mixed $value;

    public function __construct(mixed $value, string $need)
    {
        parent::__construct(
            sprintf(
                'Invalid argument given: need type "%s" got "%s"',
                $need,
                gettype($value)
            )
        );

        $this->value = $value;
    }

    public function value(): mixed
    {
        return $this->value;
    }
}
