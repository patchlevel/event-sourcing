<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\SubscriptionStream;
use Psr\Log\LoggerInterface;

use function sprintf;

/** @internal */
final class RemoveSubscriptionStreamListener
{
    public function __construct(
        private readonly Store $store,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(OnSubscriptionRemoved $event): void
    {
        $streamName = SubscriptionStream::name($event->subscription->id());

        $this->store->remove(
            new Criteria(
                new StreamCriterion($streamName),
            ),
        );

        $this->logger?->debug(
            sprintf(
                'Subscription Engine: Subscription stream "%s" for subscription "%s" has been removed.',
                $streamName,
                $event->subscription->id(),
            ),
        );
    }
}
