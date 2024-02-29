<?php

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;

use Patchlevel\EventSourcing\EventBus\Message;

class TraceDecorator implements MessageDecorator
{
    public function __construct(
        private readonly TraceStack $traceStack,
    ) {
    }

    public function __invoke(Message $message): Message
    {
        $traces = $this->traceStack->get();

        if ($traces === []) {
            return $message;
        }

        return $message->withHeader('trace', array_map(
            fn (Trace $trace) => [
                'id' => $trace->name,
                'type' => $trace->category,
            ],
            $traces
        ));
    }
}