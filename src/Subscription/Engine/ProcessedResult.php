<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use function array_merge;

final class ProcessedResult extends Result
{
    /** @param list<Error> $errors */
    public function __construct(
        public readonly int $processedMessages,
        public readonly bool $finished = false,
        array $errors = [],
    ) {
        parent::__construct($errors);
    }

    public static function empty(): self
    {
        return new self(0, true);
    }

    /** @param list<ProcessedResult> $results */
    public static function merge(array $results): self
    {
        if ($results === []) {
            return self::empty();
        }

        $processedMessages = 0;
        $finished = true;
        $errors = [];

        foreach ($results as $result) {
            $processedMessages += $result->processedMessages;
            $finished = $finished && $result->finished;
            $errors = array_merge($errors, $result->errors);
        }

        return new self($processedMessages, $finished, $errors);
    }
}
