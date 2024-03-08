<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceStack;

use function array_values;

/** @experimental */
final class TraceableProjectorAccessorRepository implements ProjectorAccessorRepository
{
    /** @var array<string, TraceableProjectorAccessor> */
    private array $projectorsMap = [];

    public function __construct(
        private readonly ProjectorAccessorRepository $parent,
        private readonly TraceStack $traceStack,
    ) {
    }

    /** @return iterable<TraceableProjectorAccessor> */
    public function all(): iterable
    {
        return array_values($this->projectorAccessorMap());
    }

    public function get(string $id): TraceableProjectorAccessor|null
    {
        $map = $this->projectorAccessorMap();

        return $map[$id] ?? null;
    }

    /** @return array<string, TraceableProjectorAccessor> */
    private function projectorAccessorMap(): array
    {
        if ($this->projectorsMap !== []) {
            return $this->projectorsMap;
        }

        foreach ($this->parent->all() as $projectorAccessor) {
            $this->projectorsMap[$projectorAccessor->id()] = new TraceableProjectorAccessor(
                $projectorAccessor,
                $this->traceStack,
            );
        }

        return $this->projectorsMap;
    }
}
