<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

class Result
{
    /** @param list<Error> $errors */
    public function __construct(
        public readonly array $errors = [],
    ) {
    }
}
