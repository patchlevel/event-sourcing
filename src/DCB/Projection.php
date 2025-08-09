<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\SubQuery;

/**
 * @experimental
 * @template S as mixed
 */
interface Projection
{
    /** @return S */
    public function initialState(): mixed;

    /**
     * @param S $state
     *
     * @return S
     */
    public function apply(mixed $state, Message $message): mixed;

    public function subQuery(): SubQuery;
}
