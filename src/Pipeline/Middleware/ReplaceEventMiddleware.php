<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\EventBucket;
use ReflectionClass;
use ReflectionProperty;

class ReplaceEventMiddleware implements Middleware
{
    /** @var class-string<AggregateChanged> */
    private string $class;

    /** @var callable(AggregateChanged $event):AggregateChanged */
    private $callable;

    private ReflectionProperty $recoredOnProperty;
    private ReflectionProperty $playheadProperty;

    /**
     * @param class-string<AggregateChanged> $class
     * @param callable(AggregateChanged      $event):AggregateChanged $callable
     */
    public function __construct(string $class, callable $callable)
    {
        $this->class = $class;
        $this->callable = $callable;

        $reflectionClass = new ReflectionClass(AggregateChanged::class);

        $this->recoredOnProperty = $reflectionClass->getProperty('recordedOn');
        $this->recoredOnProperty->setAccessible(true);

        $this->playheadProperty = $reflectionClass->getProperty('playhead');
        $this->playheadProperty->setAccessible(true);
    }

    /**
     * @return list<EventBucket>
     */
    public function __invoke(EventBucket $bucket): array
    {
        $event = $bucket->event();

        if (!$event instanceof $this->class) {
            return [$bucket];
        }

        $callable = $this->callable;

        $newEvent = $callable($event);

        $this->recoredOnProperty->setValue(
            $newEvent,
            $this->recoredOnProperty->getValue($event)
        );

        $this->playheadProperty->setValue(
            $newEvent,
            $this->playheadProperty->getValue($event)
        );

        return [
            new EventBucket(
                $bucket->aggregateClass(),
                $newEvent
            ),
        ];
    }
}
