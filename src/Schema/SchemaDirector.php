<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

interface SchemaDirector
{
    public function create(): void;

    public function update(): void;

    public function drop(): void;
}
