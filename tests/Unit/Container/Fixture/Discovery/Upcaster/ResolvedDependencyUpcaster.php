<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container\Fixture\Discovery\Upcaster;

use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Tests\Unit\Container\Fixture\Discovery\Support\TaggedDependency;

final class ResolvedDependencyUpcaster implements Upcaster
{
    public function __construct(
        private readonly TaggedDependency $dependency,
    ) {
    }

    public function __invoke(Upcast $upcast): Upcast
    {
        return $upcast->replaceEventName($upcast->eventName . '_' . $this->dependency->tag);
    }
}
