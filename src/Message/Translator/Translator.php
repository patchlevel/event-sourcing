<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;

interface Translator
{
    /** @return list<Message> */
    public function __invoke(Message $message): array;
}
