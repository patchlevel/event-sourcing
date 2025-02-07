<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\BasicChildAggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\Events\ViewTracked;

final class Views extends BasicChildAggregate
{
    public function __construct(private int $views = 0)
    {
    }

    #[Apply(ViewTracked::class)]
    public function applyNameChanged(ViewTracked $event): void
    {
        $this->views++;
    }

    public function views(): int
    {
        return $this->views;
    }

    public function trackView(): void
    {
        $this->recordThat(new ViewTracked());
    }
}
