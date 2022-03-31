<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

interface EventClassLoader
{
    /**
     * @param list<string> $paths
     *
     * @return array<string, class-string>
     */
    public function load(array $paths): array;
}
