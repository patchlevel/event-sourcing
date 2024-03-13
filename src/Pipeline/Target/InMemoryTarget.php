<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Message\Message;

final class InMemoryTarget implements Target
{
    /** @var list<Message> */
    private array $messages = [];

    public function save(Message ...$messages): void
    {
        foreach ($messages as $message) {
            $this->messages[] = $message;
        }
    }

    /** @return list<Message> */
    public function messages(): array
    {
        return $this->messages;
    }
}
