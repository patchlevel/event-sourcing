<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata;

interface ClassLocator
{
    /** @return list<class-string> */
    public function locate(): array;
}
