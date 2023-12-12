<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class SuppressMissingApply
{
    public const ALL = '*';

    /** @var list<class-string> */
    private array $suppressEvents = [];
    private bool $suppressAll = false;

    /** @param list<class-string>|self::ALL $suppress */
    public function __construct(string|array $suppress)
    {
        if ($suppress === self::ALL) {
            $this->suppressAll = true;

            return;
        }

        $this->suppressEvents = $suppress;
    }

    /** @return list<class-string> */
    public function suppressEvents(): array
    {
        return $this->suppressEvents;
    }

    public function suppressAll(): bool
    {
        return $this->suppressAll;
    }
}
