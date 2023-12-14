<?php

namespace Patchlevel\EventSourcing\Debug;

use Symfony\Component\Stopwatch\Stopwatch;

final class DefaultProfiler implements Profiler
{
    public function __construct(
        private readonly ProfileDataHolder $dataHolder,
        private readonly Stopwatch|null $stopwatch = null,
    ) {
    }

    /**
     * @param \Closure():T $closure
     *
     * @return T
     *
     * @template T of mixed
     */
    public function profile(string $name, \Closure $closure, $context = []): mixed
    {
        $data = new ProfileData($name, $context);

        $this->dataHolder->addData($data);
        $event = $this->stopwatch?->start('event_sourcing', $name);
        $data->start();

        try {
            return $closure();
        } finally {
            $data->stop();
            $event?->stop();
        }
    }
}