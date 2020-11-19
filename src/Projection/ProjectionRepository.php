<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use function get_class;
use function is_string;
use function method_exists;
use function sprintf;

final class ProjectionRepository
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

    public function handle(AggregateChanged $event): void
    {
        foreach ($this->projections as $projection) {
            $handlers = $projection::getHandledMessages();

            foreach ($handlers as $class => $method) {
                if (!$event instanceof $class) {
                    continue;
                }

                if (!is_string($method)) {
                    throw new ProjectionException(sprintf('complex "getHandledMessages" settings are not supported in %s', get_class($projection)));
                }

                if (!method_exists($projection, $method)) {
                    throw new ProjectionException(sprintf('method "%s" does not exists in %s', $method, get_class($projection)));
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
