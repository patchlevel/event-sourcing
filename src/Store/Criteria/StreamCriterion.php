<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class StreamCriterion
{
    /** @var list<string> */
    public readonly array $streamName;

    public function __construct(
        string ...$streamName,
    ) {
        $this->streamName = $streamName;
    }

    public static function startWith(string $streamName): self
    {
        return new self($streamName . '*');
    }

    public function all(): bool
    {
        foreach ($this->streamName as $name) {
            if ($name === '*') {
                return true;
            }
        }

        return false;
    }
}
