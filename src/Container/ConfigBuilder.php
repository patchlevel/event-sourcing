<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

final class ConfigBuilder
{
    private ?string $databaseUrl = null;
    private string $type = 'single';

    /** @var list<string> */
    private array $aggregates = [];

    /** @var list<string> */
    private array $events = [];

    /** @var list<string> */
    private array $projectors = [];

    /** @var list<string> */
    private array $processors = [];

    public function databaseUrl(string $url): self
    {
        $this->databaseUrl = $url;

        return $this;
    }

    public function singleTable(): self
    {
        $this->type = 'single';

        return $this;
    }

    public function multiTable(): self
    {
        $this->type = 'multi';

        return $this;
    }

    public function addAggregatePath(string $path): self
    {
        $this->aggregates[] = $path;

        return $this;
    }

    public function addEventPath(string $path): self
    {
        $this->events[] = $path;

        return $this;
    }

    public function addProjector(string $serviceId): self
    {
        $this->projectors[] = $serviceId;

        return $this;
    }

    public function addProcessor(string $serviceId): self
    {
        $this->processors[] = $serviceId;

        return $this;
    }

    public function build(): array
    {
        return [
            'connection' => [
                'url' => $this->databaseUrl,
            ],
            'store' => [
                'type' => $this->type,
            ],
            'aggregate' => [
                'paths' => $this->aggregates,
            ],
            'event' => [
                'paths' => $this->events,
            ],
            'event_bus' => [
                'listeners' => $this->processors,
            ],
            'projectors' => $this->projectors,
        ];
    }
}
