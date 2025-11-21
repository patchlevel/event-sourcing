<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use function str_contains;
use function str_replace;

/** @template T of AggregateRoot */
final class AggregateRootMetadata
{
    public readonly string $streamName;

    public function __construct(
        /** @var class-string<T> */
        public readonly string $className,
        public readonly string $name,
        public readonly string $idProperty,
        /** @var array<class-string, string> */
        public readonly array $applyMethods,
        /** @var array<class-string, true> */
        public readonly array $suppressEvents,
        public readonly bool $suppressAll,
        public readonly Snapshot|null $snapshot,
        string|null $streamName = null,
    ) {
        $this->streamName = $streamName ?? $this->name . '-{id}';
    }

    public function streamName(string|null $aggregateId = null): string
    {
        if ($aggregateId === null) {
            if (str_contains($this->streamName, '{id}')) {
                throw new MissingAggregateIdForStreamName($this->streamName);
            }

            return $this->streamName;
        }

        return str_replace('{id}', $aggregateId, $this->streamName);
    }
}
