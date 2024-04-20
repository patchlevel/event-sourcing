<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

final class Uuid implements AggregateRootId
{
    use RamseyUuidV7Behaviour;
}
