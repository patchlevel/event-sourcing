<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

final class Result
{
    /** @param list<Error> $errors */
    public function __construct(
        public readonly array $errors = [],
    ) {
    }
}
