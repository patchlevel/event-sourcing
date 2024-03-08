<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceStack;

use function array_values;

/** @experimental */
final class TraceableSubscriberAccessorRepository implements SubscriberAccessorRepository
{
    /** @var array<string, TraceableSubscriberAccessor> */
    private array $subscribersMap = [];

    public function __construct(
        private readonly SubscriberAccessorRepository $parent,
        private readonly TraceStack $traceStack,
    ) {
    }

    /** @return iterable<TraceableSubscriberAccessor> */
    public function all(): iterable
    {
        return array_values($this->subscriberAccessorMap());
    }

    public function get(string $id): TraceableSubscriberAccessor|null
    {
        $map = $this->subscriberAccessorMap();

        return $map[$id] ?? null;
    }

    /** @return array<string, TraceableSubscriberAccessor> */
    private function subscriberAccessorMap(): array
    {
        if ($this->subscribersMap !== []) {
            return $this->subscribersMap;
        }

        foreach ($this->parent->all() as $subscriberAccessor) {
            $this->subscribersMap[$subscriberAccessor->id()] = new TraceableSubscriberAccessor(
                $subscriberAccessor,
                $this->traceStack,
            );
        }

        return $this->subscribersMap;
    }
}
