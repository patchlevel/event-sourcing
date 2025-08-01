<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Query;

use function array_map;

/**
 * @experimental
 * @extends Projection<array<string, mixed>>
 */
final class CompositeProjection
{
    /** @param array<string, Projection> $projections */
    public function __construct(
        private readonly array $projections,
    ) {
    }

    public function query(): Query
    {
        $query = new Query();

        foreach ($this->projections as $projection) {
            $query = $query->add($projection->queryComponent());
        }

        return $query;
    }

    /** @return array<string, mixed> */
    public function initialState(): array
    {
        return array_map(static function (Projection $projection) {
            return $projection->initialState();
        }, $this->projections);
    }

    public function apply(mixed $state, Message $message): mixed
    {
        foreach ($this->projections as $name => $projection) {
            if (!$projection->queryComponent()->match($message)) {
                continue;
            }

            $state[$name] = $projection->apply($state[$name], $message);
        }

        return $state;
    }
}
