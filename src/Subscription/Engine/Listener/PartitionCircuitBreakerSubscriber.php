<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnResult;
use Patchlevel\EventSourcing\Subscription\Engine\FailedMessageProcessor;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ConditionalRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

use function array_keys;
use function sprintf;
use function str_contains;

/**
 * Tells a systemic failure (e.g. the database is down) apart from a failing partition. If the partitions of a
 * subscriber fail in a row without any success in between, the main subscription goes into the error status instead of
 * every single partition: the partitions that failed so far are set back, the others of the run are skipped and all
 * of them wait until the main subscription is retried.
 *
 * The state lives for one run or boot command.
 *
 * @internal
 */
final class PartitionCircuitBreakerSubscriber implements EventSubscriberInterface
{
    private bool $active = false;

    /** @var array<string, int> subscriber id => failures in a row */
    private array $failuresInARow = [];

    /** @var array<string, array<string, true>> subscriber id => failed partition ids */
    private array $failedPartitions = [];

    /** @var array<string, Throwable> subscriber id => the failure that tripped the breaker */
    private array $tripped = [];

    /** @var array<string, true> subscriber ids with a successful partition */
    private array $succeeded = [];

    /** @var array<string, array<string, array{Throwable, Message, int}>> subscriber id => partition id => held back failure */
    private array $heldBackFailures = [];

    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly RetryStrategyRepository $retryStrategyRepository,
        private readonly FailedMessageProcessor $failedMessageProcessor,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onCommand(OnCommand $event): void
    {
        $this->active = $event->command instanceof Run || $event->command instanceof Boot;
        $this->failuresInARow = [];
        $this->failedPartitions = [];
        $this->tripped = [];
        $this->succeeded = [];
        $this->heldBackFailures = [];
    }

    public function onHandleMessage(OnHandleMessage $event): void
    {
        if (!$event->subscription->isPartition() || !isset($this->tripped[$event->subscription->subscriberId()])) {
            return;
        }

        $event->stop = true;
    }

    public function onHandleMessageSuccess(OnHandleMessageSuccess $event): void
    {
        if (!$event->subscription->isPartition()) {
            return;
        }

        $subscriberId = $event->subscription->subscriberId();

        $this->failuresInARow[$subscriberId] = 0;
        $this->succeeded[$subscriberId] = true;
    }

    public function onHandleMessageError(OnHandleMessageError $event): void
    {
        if (!$this->active || !$event->subscription->isPartition()) {
            return;
        }

        $this->failure($event->subscription, $event->throwable);
    }

    /**
     * A partition without retries left would go into the failed status and call its #[OnFailed] method, which may
     * skip the message. During a systemic failure that must not happen, so the transition is held back until the end
     * of the command: the partition goes into the error status for now and only fails if the breaker did not trip.
     */
    public function holdBackFailure(OnHandleMessageError $event): void
    {
        if (!$this->active || !$event->transitionToFailed || !$event->subscription->isPartition()) {
            return;
        }

        $partition = $event->subscription;

        $event->transitionToFailed = false;
        $partition->error($event->throwable);
        $this->subscriptionManager->update($partition);

        $this->heldBackFailures[$partition->subscriberId()][$partition->id()] = [
            $event->throwable,
            $event->message,
            $event->index,
        ];
    }

    /**
     * Failures outside of a message, like a failing batch flush or a whole group, only show up in the result.
     * Then the main subscriptions of tripped subscribers go into error and their failed partitions are set back.
     */
    public function onResult(OnResult $event): void
    {
        if (!$this->active) {
            return;
        }

        foreach ($event->result->errors as $error) {
            // the id of a partition always contains the separator, the one of a partitioned subscriber never
            if (!str_contains($error->subscriptionId, Subscription::PARTITION_SEPARATOR) || $this->isRecorded($error->subscriptionId)) {
                continue;
            }

            $partitions = $this->subscriptionManager->find(new SubscriptionCriteria(ids: [$error->subscriptionId], partitions: true));

            foreach ($partitions as $partition) {
                if ($partition->id() !== $error->subscriptionId) {
                    continue;
                }

                $this->failure($partition, $error->throwable);
            }
        }

        foreach ($this->tripped as $subscriberId => $throwable) {
            if ($this->stopSubscriber($subscriberId, $throwable)) {
                // the partitions did not fail on their own, they are set back
                unset($this->heldBackFailures[$subscriberId]);

                continue;
            }

            unset($this->tripped[$subscriberId]);
        }

        foreach ($this->heldBackFailures as $failures) {
            $this->fail($failures);
        }

        $this->resetRetries();

        $this->active = false;
    }

    private function failure(Subscription $partition, Throwable $throwable): void
    {
        $subscriberId = $partition->subscriberId();

        $this->failedPartitions[$subscriberId][$partition->id()] = true;

        if (isset($this->tripped[$subscriberId])) {
            return;
        }

        $this->failuresInARow[$subscriberId] = ($this->failuresInARow[$subscriberId] ?? 0) + 1;

        $maxConsecutiveFailures = $this->subscriberRepository->get($subscriberId)?->metadata()->partition?->maxConsecutiveFailures;

        if ($maxConsecutiveFailures === null || $this->failuresInARow[$subscriberId] < $maxConsecutiveFailures) {
            return;
        }

        $this->tripped[$subscriberId] = $throwable;

        $this->logger?->warning(sprintf(
            'Subscription Engine: %d partitions of subscriber "%s" failed in a row, the subscriber is stopped: %s',
            $this->failuresInARow[$subscriberId],
            $subscriberId,
            $throwable->getMessage(),
        ));
    }

    private function isRecorded(string $subscriptionId): bool
    {
        foreach ($this->failedPartitions as $partitionIds) {
            if (isset($partitionIds[$subscriptionId])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the main subscription is stopped. Only then the failed partitions are set back, otherwise they
     * would start again right away without any backoff.
     */
    private function stopSubscriber(string $subscriberId, Throwable $throwable): bool
    {
        $stopped = $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(ids: [$subscriberId], status: [Status::Active, Status::Booting], partitions: false),
            function (Subscription $main) use ($throwable): bool {
                if ($this->canRetry($main)) {
                    $main->error($throwable);
                } else {
                    $main->failed($throwable);
                }

                $this->subscriptionManager->update($main);

                return true;
            },
            wait: true,
        );

        if ($stopped === [] && !$this->isStopped($subscriberId)) {
            $this->logger?->warning(sprintf(
                'Subscription Engine: The main subscription of subscriber "%s" could not be stopped, it is held by another worker.',
                $subscriberId,
            ));

            return false;
        }

        $partitionIds = array_keys($this->failedPartitions[$subscriberId] ?? []);

        if ($partitionIds === []) {
            return true;
        }

        // the partitions did not fail on their own, they continue where they were once the subscriber runs again
        $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(ids: $partitionIds, status: [Status::Error, Status::Failed], partitions: true),
            function (Subscription $partition): void {
                if ($partition->subscriptionError()?->previousStatus === Status::Booting) {
                    $partition->booting();
                } else {
                    $partition->active();
                }

                $this->subscriptionManager->update($partition);
            },
            wait: true,
        );

        return true;
    }

    /** E.g. by another worker whose breaker tripped as well. */
    private function isStopped(string $subscriberId): bool
    {
        foreach ($this->subscriptionManager->find(new SubscriptionCriteria(ids: [$subscriberId], partitions: false)) as $main) {
            if ($main->isError() || $main->isFailed()) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, array{Throwable, Message, int}> $failures partition id => held back failure */
    private function fail(array $failures): void
    {
        $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(ids: array_keys($failures), status: [Status::Error], partitions: true),
            function (Subscription $partition) use ($failures): void {
                if (!isset($failures[$partition->id()])) {
                    return;
                }

                [$throwable, $message, $index] = $failures[$partition->id()];

                // back to the status before the error, the failed message processor decides about the rest
                $partition->doRetry();
                $this->failedMessageProcessor->process($partition, $throwable, $message, $index);
            },
            wait: true,
        );
    }

    /** The main subscription only routes, its retries are reset once its partitions succeed again. */
    private function resetRetries(): void
    {
        $subscriberIds = [];

        foreach (array_keys($this->succeeded) as $subscriberId) {
            if (isset($this->tripped[$subscriberId])) {
                continue;
            }

            $subscriberIds[] = $subscriberId;
        }

        if ($subscriberIds === []) {
            return;
        }

        $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(ids: $subscriberIds, status: [Status::Active, Status::Booting], partitions: false),
            function (Subscription $main): void {
                $main->resetRetry();
                $this->subscriptionManager->update($main);
            },
            filter: static fn (Subscription $main): bool => $main->retryAttempt() > 0,
        );
    }

    private function canRetry(Subscription $main): bool
    {
        $name = $this->subscriberRepository->get($main->subscriberId())?->metadata()->retryStrategy;
        $retryStrategy = $name === null
            ? $this->retryStrategyRepository->getDefaultRetryStrategy()
            : $this->retryStrategyRepository->get($name);

        return !$retryStrategy instanceof ConditionalRetryStrategy || $retryStrategy->canRetry($main);
    }

    /** @return array<class-string, string|array{string, int}|list<array{string, int}>> */
    public static function getSubscribedEvents(): array
    {
        return [
            OnCommand::class => 'onCommand',
            // before the batch subscriber, a skipped message must not open a batch
            OnHandleMessage::class => ['onHandleMessage', 32],
            OnHandleMessageSuccess::class => 'onHandleMessageSuccess',
            OnHandleMessageError::class => [
                ['onHandleMessageError', 0],
                // after the retry subscriber decided about the transition, before the fail subscriber executes it
                ['holdBackFailure', -4],
            ],
            OnResult::class => 'onResult',
        ];
    }
}
