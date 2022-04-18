<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

/**
 * @readonly
 */
final class AggregateRootMetadata
{
    public function __construct(
        /** @var array<class-string, string> */
        public array $applyMethods,
        /** @var array<class-string, true> */
        public array $suppressEvents,
        public bool $suppressAll,
    ) {
    }
}
