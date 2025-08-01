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

    public function add(QueryComponent $component): self
    {
        foreach ($this->components as $c) {
            if ($c->equals($component)) {
                return $this;
            }
        }

        $components = [...$this->components, $component];

        return new self(...$components);
    }
}
