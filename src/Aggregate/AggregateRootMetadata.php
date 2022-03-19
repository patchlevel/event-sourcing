<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

/**
 * @internal
 */
final class AggregateRootMetadata
{
    /** @var array<class-string, true> */
    public array $suppressEvents = [];

    public bool $suppressAll = false;

    /** @var array<class-string, string> */
    public array $applyMethods = [];
}
