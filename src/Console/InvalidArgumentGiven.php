<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use InvalidArgumentException;

use function gettype;
use function sprintf;

final class InvalidArgumentGiven extends InvalidArgumentException
{
    public function __construct(
        private mixed $value,
        string $need,
    ) {
        parent::__construct(
            sprintf(
                'Invalid argument given: need type "%s" got "%s"',
                $need,
                gettype($value),
            ),
        );
    }

    public function value(): mixed
    {
        return $this->value;
    }
}
