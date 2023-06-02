<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Event
{
    public function __construct(
        private string $name,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }
}
