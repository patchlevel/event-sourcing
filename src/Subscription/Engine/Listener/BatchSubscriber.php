<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnProcessingFinished;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

use function sprintf;

/** @internal */
final class BatchSubscriber implements EventSubscriberInterface
{
    /** @var array<string, array{subscriber: BatchableSubscriber, subscription: Subscription}> */
    private array $batching = [];

    public function __construct(
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onCommand(OnCommand $event): void
    {
        $this->batching = [];
    }

    public function onHandleMessage(OnHandleMessage $event): void
    {
        $subscriberId = $event->subscription->id();

        if (isset($this->batching[$subscriberId])) {
            return;
        }

        $subscriber = $this->subscriberRepository->get($subscriberId);

        if (!$subscriber) {
            return;
        }

        $realSubscriber = $subscriber->subscriber();

        if (!$realSubscriber instanceof BatchableSubscriber) {
            return;
        }

        $this->batching[$subscriberId] = [
            'subscriber' => $realSubscriber,
            'subscription' => $event->subscription,
        ];

        $this->logger?->debug(sprintf(
            'Subscription Engine: Subscriber "%s" starts a new batch.',
            $subscriberId,
        ));

        try {
            $realSubscriber->beginBatch();
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the begin batch method: %s',
                $subscriberId,
                $e->getMessage(),
            ));

            throw $e;
        }
    }

    public function onHandleMessageSuccess(OnHandleMessageSuccess $event): void
    {
        $subscriberId = $event->subscription->id();

        if (!isset($this->batching[$subscriberId])) {
            return;
        }

        if (!$this->shouldCommitBatch($event->subscription)) {
            $event->shouldChangePosition = false;

            return;
        }

        $subscriber = $this->batching[$subscriberId]['subscriber'];
        unset($this->batching[$subscriberId]);

        $this->logger?->debug(sprintf(
            'Subscription Engine: Subscriber "%s" commits the batch.',
            $subscriberId,
        ));

        try {
            $subscriber->commitBatch();
            $event->shouldChangePosition = true;
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the commit batch method: %s',
                $subscriberId,
                $e->getMessage(),
            ));

            throw $e;
        }
    }

    public function onProcessingFinished(OnProcessingFinished $event): void
    {
        $lastIndex = $event->lastIndex;

        if ($lastIndex === null) {
            return;
        }

        foreach ($this->batching as $subscriberId => ['subscriber' => $subscriber, 'subscription' => $subscription]) {
            unset($this->batching[$subscriberId]);

            $this->logger?->debug(sprintf(
                'Subscription Engine: Subscriber "%s" commits the batch.',
                $subscriberId,
            ));

            try {
                $subscriber->commitBatch();
                $subscription->changePosition($lastIndex);
            } catch (Throwable $e) {
                $this->logger?->error(sprintf(
                    'Subscription Engine: Subscriber "%s" has an error in the commit batch method: %s',
                    $subscriberId,
                    $e->getMessage(),
                ));

                $subscription->error($e);
                $event->errors[] = new Error($subscriberId, $e->getMessage(), $e);
            }
        }
    }

    private function shouldCommitBatch(Subscription $subscription): bool
    {
        return $this->batching[$subscription->id()]['subscriber']->forceCommit();
    }

    public function onError(OnHandleMessageError $event): void
    {
        $subscriptionId = $event->subscription->id();

        if (!isset($this->batching[$subscriptionId])) {
            return;
        }

        $subscriber = $this->batching[$subscriptionId]['subscriber'];

        unset($this->batching[$subscriptionId]);

        $this->logger?->debug(sprintf(
            'Subscription Engine: Subscriber "%s" rollback the batch.',
            $subscriptionId,
        ));

        try {
            $subscriber->rollbackBatch();
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the rollback batch method: %s',
                $subscriptionId,
                $e->getMessage(),
            ));
        }
    }

    /** @return array<class-string, string|array{string, int}> */
    public static function getSubscribedEvents(): array
    {
        return [
            OnCommand::class => 'onCommand',
            OnHandleMessage::class => 'onHandleMessage',
            OnHandleMessageSuccess::class => 'onHandleMessageSuccess',
            OnHandleMessageError::class => 'onError',
            OnProcessingFinished::class => 'onProcessingFinished',
        ];
    }
}
