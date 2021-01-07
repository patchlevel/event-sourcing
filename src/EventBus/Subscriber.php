<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface Subscriber
{
    /** @return iterable<class-string<AggregateChanged>> */
    public function getHandledMessages(): iterable;

    public function __invoke(AggregateChanged $event): void;
}
