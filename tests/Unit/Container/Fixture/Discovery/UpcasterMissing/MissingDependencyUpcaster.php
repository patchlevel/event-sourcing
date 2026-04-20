<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container\Fixture\Discovery\UpcasterMissing;

use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Tests\Unit\Container\Fixture\Discovery\Support\MissingDependency;

final class MissingDependencyUpcaster implements Upcaster
{
    public function __construct(
        private readonly MissingDependency $dependency,
    ) {
    }

    public function __invoke(Upcast $upcast): Upcast
    {
        return $upcast->replacePayloadByKey('missing_dependency', $this->dependency::class);
    }
}
