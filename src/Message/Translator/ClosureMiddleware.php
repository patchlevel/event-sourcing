<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Closure;
use Patchlevel\EventSourcing\Message\Message;

final class ClosureMiddleware implements Translator
{
    /** @param Closure(Message): list<Message> $callable */
    public function __construct(
        private readonly Closure $callable,
    ) {
    }

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        return ($this->callable)($message);
    }
}
