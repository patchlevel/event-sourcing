<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

interface Projection
{
    /**
     * @return iterable<class-string, string>
     */
    abstract public function getHandledMessages(): iterable;

    abstract public function drop(): void;
}
