<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Message\Message;

final class InMemoryTarget implements Target
{
    /** @var list<Message> */
    private array $messages = [];

    public function save(Message ...$message): void
    {
        foreach ($message as $m) {
        	$this->messages[] = $m;
        }
    }

    /** @return list<Message> */
    public function messages(): array
    {
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}