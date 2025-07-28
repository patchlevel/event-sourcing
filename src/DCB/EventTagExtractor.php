<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

/** @experimental */
interface EventTagExtractor
{
    /** @return list<string> */
    public function extract(object $event): array;
}
