<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function get_class;
use function method_exists;

final class DefaultProjectionRepository implements ProjectionRepository
{
    /** @var iterable<Projection> */
    private iterable $projections;

    /**
     * @param iterable<Projection> $projections
     */
    public function __construct(iterable $projections)
    {
        $this->projections = $projections;
    }

    public function handle(AggregateChanged $event): void
    {
        foreach ($this->projections as $projection) {
            $handlers = $projection->handledEvents();

            foreach ($handlers as $class => $method) {
                /** @psalm-suppress DocblockTypeContradiction */
                if (!$event instanceof $class) {
                    continue;
                }

                if (!method_exists($projection, $method)) {
                    throw new MethodDoesNotExist(get_class($projection), $method);
                }

                $projection->$method($event);
            }
        }
    }

    public function create(): void
    {
        foreach ($this->projections as $projection) {
            $projection->create();
        }
    }

    public function drop(): void
    {
        foreach ($this->projections as $projection) {
            $projection->drop();
        }
    }
}
