<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Message\Message;

/**
 * @experimental
 * @template S of mixed
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
}
