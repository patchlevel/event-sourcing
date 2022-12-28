<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use function sprintf;

final class DuplicateHandleMethod extends EventBusException
{
    /**
     * @param class-string<Subscriber> $subscriber
     * @param class-string             $event
     */
    public function __construct(string $subscriber, string $event, string $fistMethod, string $secondMethod)
    {
        parent::__construct(
            sprintf(
                'Two methods "%s" and "%s" on the subscriber "%s" want to handle the same event "%s". Only one method can handle an event.',
                $fistMethod,
                $secondMethod,
                $subscriber,
                $event
            )
        );
    }
}
