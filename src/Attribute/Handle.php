<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Handle
{
    /** @param class-string $eventClass */
    public function __construct(private string $eventClass)
    {
    }

    /** @return class-string */
    public function eventClass(): string
    {
        return $this->eventClass;
    }
}
