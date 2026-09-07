<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Command;

final class Run extends Command
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     * @param positive-int|null $limit
     */
    public function __construct(
        array|null $ids = null,
        array|null $groups = null,
        public readonly int|null $limit = null,
    ) {
        parent::__construct($ids, $groups);
    }
}
