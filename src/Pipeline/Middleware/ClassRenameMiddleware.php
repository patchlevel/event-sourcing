<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\EventBucket;

use function array_key_exists;
use function get_class;

class ClassRenameMiddleware implements Middleware
{
    /** @var array<class-string<AggregateChanged>, class-string<AggregateChanged>> */
    private array $classes;

    /**
     * @param array<class-string<AggregateChanged>, class-string<AggregateChanged>> $classes
     */
    public function __construct(array $classes)
    {
        $this->classes = $classes;
    }

    /**
     * @return list<EventBucket>
     */
    public function __invoke(EventBucket $bucket): array
    {
        $event = $bucket->event();
        $class = get_class($event);

        if (!array_key_exists($class, $this->classes)) {
            return [$bucket];
        }

        $data = $event->serialize();
        $data['event'] = $this->classes[$class];

        $newEvent = AggregateChanged::deserialize($data);

        return [
            new EventBucket(
                $bucket->aggregateClass(),
                $bucket->index(),
                $newEvent
            ),
        ];
    }
}
