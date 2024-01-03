<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class SuppressMissingApply
{
    public const ALL = '*';

    /** @var list<class-string> */
    public readonly array $suppressEvents;
    public readonly bool $suppressAll;

    /** @param list<class-string>|self::ALL $suppress */
    public function __construct(string|array $suppress)
    {
        if ($suppress === self::ALL) {
            $this->suppressEvents = [];
            $this->suppressAll = true;

            return;
        }

        $this->suppressEvents = $suppress;
        $this->suppressAll = false;
    }
}
