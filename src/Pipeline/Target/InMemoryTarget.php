<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;

final class InMemoryTarget implements Target
{
    /** @var list<Message> */
    private array $messages = [];

    public function save(Message $message): void
    {
        $this->messages[] = $message;
    }

    /** @return list<Message> */
    public function messages(): array
    {
        return $this->messages;
    }
}
