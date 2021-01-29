<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Store\StreamableStore;

class ProjectionPipeline
{
    private StreamableStore $store;
    private ProjectionRepository $projectionRepository;

    public function __construct(StreamableStore $store, ProjectionRepository $projectionRepository)
    {
        $this->store = $store;
        $this->projectionRepository = $projectionRepository;
    }

    /**
     * @param callable(AggregateChanged $event):void|null $observer
     */
    public function rebuild(?callable $observer = null): void
    {
        if ($observer === null) {
            $observer = static function (AggregateChanged $event): void {
            };
        }

        $this->projectionRepository->drop();

        foreach ($this->store->all() as $event) {
            $observer($event);

            $this->projectionRepository->handle($event);
        }
    }
}
