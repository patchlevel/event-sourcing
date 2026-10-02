<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata;

final class InMemoryClassLocator implements ClassLocator
{
    /** @param list<class-string> $classes */
    public function __construct(
        private readonly array $classes,
    ) {
    }

    /** @return list<class-string> */
    public function locate(): array
    {
        return $this->classes;
    }
}
