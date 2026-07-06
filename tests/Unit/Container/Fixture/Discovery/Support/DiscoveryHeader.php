<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container\Fixture\Discovery\Support;

final class DiscoveryHeader
{
    public function __construct(
        public readonly string $tag,
    ) {
    }
}
