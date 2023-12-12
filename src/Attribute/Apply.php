<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Apply
{
    /** @param class-string|null $eventClass */
    public function __construct(private string|null $eventClass = null)
    {
    }

    /** @return class-string|null */
    public function eventClass(): string|null
    {
        return $this->eventClass;
    }
}
