<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Apply
{
    /** @var class-string */
    private string $eventClass;

    /**
     * @param class-string $eventClass
     */
    public function __construct(string $eventClass)
    {
        $this->eventClass = $eventClass;
    }

    /**
     * @return class-string
     */
    public function eventClass(): string
    {
        return $this->eventClass;
    }
}
