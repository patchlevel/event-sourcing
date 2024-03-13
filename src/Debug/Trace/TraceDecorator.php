<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug\Trace;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;

use function array_map;

/** @experimental */
final class TraceDecorator implements MessageDecorator
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

        return $message->withHeader(new TraceHeader(
            array_map(
                static fn (Trace $trace) => [
                    'name' => $trace->name,
                    'category' => $trace->category,
                ],
                $traces,
            ),
        ));
    }
}
