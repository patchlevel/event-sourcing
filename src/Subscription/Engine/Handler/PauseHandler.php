<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Throwable;

use function sprintf;

/**
 * @internal
 *
 * @implements Handler<Pause>
 */
final class PauseHandler implements Handler
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): Result
    {
        $results = $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [
                    Status::Active,
                    Status::Booting,
                    Status::Error,
                ],
            ),
            function (Subscription $subscription): Result {
                $subscription->pause();
                $this->subscriptionManager->update($subscription);

                $this->logger?->info(sprintf(
                    'Subscription Engine: Subscription "%s" is paused.',
                    $subscription->id(),
                ));

                return new Result();
            },
            static fn (Subscription $subscription, Throwable $e): Result => new Result(
                [new Error($subscription->id(), $e->getMessage(), $e)],
            ),
        );

        return Result::merge($results);
    }
}
