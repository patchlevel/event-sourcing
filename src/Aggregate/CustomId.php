<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

final class CustomId implements AggregateRootId
{
    use CustomIdBehaviour;
}
