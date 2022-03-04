<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

/**
 * @internal
 */
final class AggregateRootMetadata
{
    /** @var array<class-string<AggregateChanged>, true> */
    public array $suppressEvents = [];

    public bool $suppressAll = false;

    /** @var array<class-string<AggregateChanged>, string> */
    public array $applyMethods = [];
}
