<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

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
