<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use function array_merge;

class Result
{
    /** @param list<Error> $errors */
    public function __construct(
        public readonly array $errors = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /** @param list<Result> $results */
    public static function merge(array $results): self
    {
        $errors = [];

        foreach ($results as $result) {
            $errors = array_merge($errors, $result->errors);
        }

        return new self($errors);
    }
}
