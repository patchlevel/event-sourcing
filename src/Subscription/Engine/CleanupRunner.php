<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Cleanup\Cleaner;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Throwable;

use function sprintf;

/** @internal */
final class CleanupRunner
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly Cleaner|null $cleaner = null,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function cleanup(Subscription $subscription, bool $force = false): Error|null
    {
        if (!$this->cleaner) {
            throw new CleanerNotConfigured();
        }

        try {
            $this->cleaner->cleanup($subscription);

            $this->logger?->debug(
                sprintf(
                    'Subscription Engine: For Subscription "%s" the cleanup tasks have been executed.',
                    $subscription->id(),
                ),
            );
        } catch (Throwable $e) {
            $this->logger?->error(
                sprintf(
                    'Subscription Engine: Subscription "%s" has an error in the cleanup tasks: %s',
                    $subscription->id(),
                    $e->getMessage(),
                ),
            );

            if ($force) {
                $this->subscriptionManager->remove($subscription);

                $this->logger?->info(sprintf(
                    'Subscription Engine: Subscription "%s" removed.',
                    $subscription->id(),
                ));
            }

            return new Error(
                $subscription->id(),
                $e->getMessage(),
                $e,
            );
        }

        $this->subscriptionManager->remove($subscription);

        $this->logger?->info(sprintf(
            'Subscription Engine: Subscription "%s" removed.',
            $subscription->id(),
        ));

        return null;
    }
}
