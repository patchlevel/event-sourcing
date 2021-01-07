<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\Projection\Exception\MethodDoesNotExist;
use function get_class;
use function method_exists;

final class ProjectionRepository implements Listener
{
    /**
     * @var iterable<Projection>
     */
    private iterable $projections;

    /**
     * @param iterable<Projection> $projections
     */
    public function __construct(iterable $projections)
    {
        $this->projections = $projections;
    }

    public function __invoke(AggregateChanged $event): void
    {
        foreach ($this->projections as $projection) {
            $handlers = $projection->getHandledMessages();

            foreach ($handlers as $class => $method) {
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

    public function drop(): void
    {
        foreach ($this->projections as $projection) {
            $projection->drop();
        }
    }
}
