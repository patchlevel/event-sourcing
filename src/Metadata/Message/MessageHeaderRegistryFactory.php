<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\ClassLocator;

interface MessageHeaderRegistryFactory
{
    public function create(ClassLocator $locator): MessageHeaderRegistry;
}
