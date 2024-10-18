<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;

/** @deprecated use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware instead */
interface Translator
{
    /** @return list<Message> */
    public function __invoke(Message $message): array;
}
