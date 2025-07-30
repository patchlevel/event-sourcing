<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

final class Query
{
    /** @var list<QueryComponent> */
    public readonly array $components;

    public function __construct(
        QueryComponent ...$components,
    ) {
        $this->components = $components;
    }
}
