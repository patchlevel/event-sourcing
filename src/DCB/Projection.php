<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Message\Message;
use ReflectionClass;

use function method_exists;

/**
 * @experimental
 * @template S as mixed
 */
abstract class Projection
{
    /** @return list<string> */
    abstract public function tagFilter(): array;

    /** @return S */
    abstract public function initialState(): mixed;

    /**
     * @param S $state
     *
     * @return S
     */
    public function apply(mixed $state, Message $message): mixed
    {
        $event = $message->event();

        $method = 'apply' . (new ReflectionClass($event))->getShortName();

        if (method_exists($this, $method)) {
            $state = $this->{$method}($state, $event);
        }

        return $state;
    }
}
