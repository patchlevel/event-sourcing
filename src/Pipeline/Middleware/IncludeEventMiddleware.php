<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Message;

final class IncludeEventMiddleware implements Middleware
{
    /** @var list<class-string<AggregateChanged>> */
    private array $classes;

    /**
     * @param list<class-string<AggregateChanged>> $classes
     */
    public function __construct(array $classes)
    {
        $this->classes = $classes;
    }

    /**
     * @return list<Message>
     */
    public function __invoke(Message $message): array
    {
        foreach ($this->classes as $class) {
            if ($message->event() instanceof $class) {
                return [$message];
            }
        }

        return [];
    }
}
