<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Message;

final class FilterEventMiddleware implements Middleware
{
    /** @var callable(AggregateChanged $event):bool */
    private $callable;

    /**
     * @param callable(AggregateChanged $event):bool $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @return list<Message>
     */
    public function __invoke(Message $message): array
    {
        $result = ($this->callable)($message->event());

        if ($result) {
            return [$message];
        }

        return [];
    }
}
