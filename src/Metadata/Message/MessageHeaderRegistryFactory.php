<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

interface MessageHeaderRegistryFactory
{
    /** @param list<string> $paths */
    public function create(array $paths): MessageHeaderRegistry;
}
