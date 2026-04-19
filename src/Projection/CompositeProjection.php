<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Query;

use function array_map;

/** @experimental */
final class CompositeProjection
{
    /** @param array<string, Projection> $projections */
    public function __construct(
        private readonly array $projections,
    ) {
    }

    public function query(): Query
    {
        $subQueries = [];

        foreach ($this->projections as $projection) {
            if (!$projection instanceof SubQueryProvider) {
                return new Query();
            }

            $subQueries[] = $projection->subQuery();
        }

        $query = new Query(...$subQueries);

        return $query->optimize();
    }

    /** @return array<string, mixed> */
    public function initialState(): array
    {
        return array_map(static function (Projection $projection) {
            return $projection->initialState();
        }, $this->projections);
    }

    /**
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    public function apply(mixed $state, Message $message): mixed
    {
        foreach ($this->projections as $name => $projection) {
            $state[$name] = $projection->apply($state[$name], $message);
        }

        return $state;
    }
}
