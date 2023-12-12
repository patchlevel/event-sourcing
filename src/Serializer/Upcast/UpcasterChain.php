<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Upcast;

final class UpcasterChain implements Upcaster
{
    /** @param iterable<Upcaster> $upcaster */
    public function __construct(
        private readonly iterable $upcaster,
    ) {
    }

    public function __invoke(Upcast $upcast): Upcast
    {
        foreach ($this->upcaster as $upcaster) {
            $upcast = $upcaster($upcast);
        }

        return $upcast;
    }
}
