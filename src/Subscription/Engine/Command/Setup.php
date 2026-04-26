<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Command;

final class Setup extends Command
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     */
    public function __construct(
        array|null $ids = null,
        array|null $groups = null,
        public readonly bool $skipBooting = false,
    ) {
        parent::__construct($ids, $groups);
    }
}
