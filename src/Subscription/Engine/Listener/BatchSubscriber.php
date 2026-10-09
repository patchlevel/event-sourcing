<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionProcessed;
use Patchlevel\EventSourcing\Subscription\Subscriber\Batch;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchManager;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

use function is_object;
use function sprintf;

/** @internal */
final class BatchSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BatchManager $batchManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onCommand(OnCommand $event): void
    {
        $this->batchManager->clear();
    }

    public function onHandleMessage(OnHandleMessage $event): void
    {
        $subscriberId = $event->subscription->id();

        if ($this->batchManager->has($subscriberId)) {
            return;
        }

        $subscriber = $this->subscriberRepository->get($subscriberId);
        $batchMetadata = $subscriber?->metadata()->batch;

        if ($subscriber === null || $batchMetadata === null) {
            return;
        }

        $realSubscriber = $subscriber->subscriber();

        $this->logger?->debug(sprintf(
            'Subscription Engine: Subscriber "%s" starts a new batch.',
            $subscriberId,
        ));

        $beginMethod = $batchMetadata->beginMethod;

        try {
            $state = $beginMethod !== null ? $realSubscriber->$beginMethod() : null;
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the begin batch method: %s',
                $subscriberId,
                $e->getMessage(),
            ));

            throw $e;
        }

        if ($state !== null && !is_object($state)) {
            throw new InvalidArgumentException(sprintf(
                'Subscription Engine: Subscriber "%s" begin batch method must return an object or null.',
                $subscriberId,
            ));
        }

        $this->batchManager->add(new Batch(
            $event->subscription,
            $subscriber,
            $state ?? new stdClass(),
        ));
    }

    public function onHandleMessageSuccess(OnHandleMessageSuccess $event): void
    {
        $subscriberId = $event->subscription->id();

        if (!$this->batchManager->has($subscriberId)) {
            return;
        }

        $batch = $this->batchManager->get($subscriberId);
        $batch->count++;

        if (!$this->shouldFlush($batch)) {
            $event->shouldChangePosition = false;

            return;
        }

        $this->batchManager->remove($subscriberId);

        $this->logger?->debug(sprintf(
            'Subscription Engine: Subscriber "%s" flushes the batch.',
            $subscriberId,
        ));

        $flushMethod = $batch->accessor->metadata()->batch?->flushMethod;

        if ($flushMethod === null) {
            $event->shouldChangePosition = true;

            return;
        }

        try {
            $batch->accessor->subscriber()->$flushMethod($batch->state);
            $event->shouldChangePosition = true;
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the flush method: %s',
                $subscriberId,
                $e->getMessage(),
            ));

            throw $e;
        }
    }

    public function onSubscriptionProcessed(OnSubscriptionProcessed $event): void
    {
        $lastIndex = $event->lastIndex;

        if ($lastIndex === null) {
            return;
        }

        $subscription = $event->subscription;
        $subscriberId = $subscription->id();

        if (!$this->batchManager->has($subscriberId)) {
            return;
        }

        $batch = $this->batchManager->get($subscriberId);
        $this->batchManager->remove($subscriberId);

        $flushMethod = $batch->accessor->metadata()->batch?->flushMethod;

        if ($flushMethod === null) {
            $subscription->changePosition($lastIndex);

            return;
        }

        $this->logger?->debug(sprintf(
            'Subscription Engine: Subscriber "%s" flushes the batch.',
            $subscriberId,
        ));

        try {
            $batch->accessor->subscriber()->$flushMethod($batch->state);
            $subscription->changePosition($lastIndex);
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the flush method: %s',
                $subscriberId,
                $e->getMessage(),
            ));

            $subscription->error($e);
            $event->errors[] = new Error($subscriberId, $e->getMessage(), $e);
        }
    }

    public function onError(OnHandleMessageError $event): void
    {
        $subscriptionId = $event->subscription->id();

        if (!$this->batchManager->has($subscriptionId)) {
            return;
        }

        $batch = $this->batchManager->get($subscriptionId);
        $this->batchManager->remove($subscriptionId);

        $rollbackMethod = $batch->accessor->metadata()->batch?->rollbackMethod;

        if ($rollbackMethod === null) {
            return;
        }

        $this->logger?->debug(sprintf(
            'Subscription Engine: Subscriber "%s" rollback the batch.',
            $subscriptionId,
        ));

        try {
            $batch->accessor->subscriber()->$rollbackMethod($batch->state);
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the rollback batch method: %s',
                $subscriptionId,
                $e->getMessage(),
            ));
        }
    }

    private function shouldFlush(Batch $batch): bool
    {
        $metadata = $batch->accessor->metadata()->batch;

        if ($metadata?->afterMessages !== null && $batch->count >= $metadata->afterMessages) {
            return true;
        }

        $shouldFlushMethod = $metadata?->shouldFlushMethod;

        if ($shouldFlushMethod === null) {
            return false;
        }

        return $batch->accessor->subscriber()->$shouldFlushMethod($batch->state) === true;
    }

    /** @return array<class-string, string|array{string, int}> */
    public static function getSubscribedEvents(): array
    {
        return [
            OnCommand::class => 'onCommand',
            OnHandleMessage::class => 'onHandleMessage',
            OnHandleMessageSuccess::class => 'onHandleMessageSuccess',
            OnHandleMessageError::class => 'onError',
            OnSubscriptionProcessed::class => 'onSubscriptionProcessed',
        ];
    }
}
