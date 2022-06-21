<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Apply
{
    /** @var class-string|null */
    private ?string $eventClass;

    /**
     * @param class-string|null $eventClass
     */
    public function __construct(?string $eventClass = null)
    {
        $this->eventClass = $eventClass;
    }

    /**
     * @return class-string|null
     */
    public function eventClass(): ?string
    {
        return $this->eventClass;
    }
}
