<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\LockableSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Throwable;

use function count;
use function in_array;
use function sprintf;

final class DefaultSubscriptionEngine implements SubscriptionEngine
{
    public function __construct(
        private readonly Store $messageStore,
        private readonly SubscriptionStore $subscriptionStore,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly RetryStrategy $retryStrategy = new ClockBasedRetryStrategy(),
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function boot(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): void {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->logger?->info(
            'Subscription Engine: Start booting.',
        );

        $this->discoverNewSubscriptions();
        $this->retrySubscriptions($criteria);
        $this->setupNewSubscriptions($criteria);

        $this->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [Status::Booting],
            ),
            function ($subscriptions) use ($limit): void {
                $subscriptions = $this->fastForwardFromNowSubscriptions($subscriptions);

                if (count($subscriptions) === 0) {
                    $this->logger?->info('Subscription Engine: No subscriptions in booting status, finish booting.');

                    return;
                }

                $startIndex = $this->lowestSubscriptionPosition($subscriptions);

                $this->logger?->debug(
                    sprintf(
                        'Subscription Engine: Event stream is processed for booting from position %s.',
                        $startIndex,
                    ),
                );

                $stream = null;
                $messageCounter = 0;

                try {
                    $stream = $this->messageStore->load(
                        new Criteria(fromIndex: $startIndex),
                    );

                    foreach ($stream as $message) {
                        $index = $stream->index();

                        if ($index === null) {
                            throw new UnexpectedError('Stream index is null, this should not happen.');
                        }

                        foreach ($subscriptions as $subscription) {
                            if (!$subscription->isBooting()) {
                                continue;
                            }

                            if ($subscription->position() >= $index) {
                                $this->logger?->debug(
                                    sprintf(
                                        'Subscription Engine: Subscription "%s" is farther than the current position (%d > %d), continue booting.',
                                        $subscription->id(),
                                        $subscription->position(),
                                        $index,
                                    ),
                                );

                                continue;
                            }

                            $this->handleMessage($index, $message, $subscription);
                        }

                        $messageCounter++;

                        $this->logger?->debug(
                            sprintf(
                                'Subscription Engine: Current event stream position for booting: %s',
                                $index,
                            ),
                        );

                        if ($limit !== null && $messageCounter >= $limit) {
                            $this->logger?->info(
                                sprintf(
                                    'Subscription Engine: Message limit (%d) reached, finish booting.',
                                    $limit,
                                ),
                            );

                            return;
                        }
                    }
                } finally {
                    if ($messageCounter > 0) {
                        foreach ($subscriptions as $subscription) {
                            if (!$subscription->isBooting()) {
                                continue;
                            }

                            $this->subscriptionStore->update($subscription);
                        }
                    }

                    $stream?->close();
                }

                $this->logger?->debug('Subscription Engine: End of stream for booting has been reached.');

                foreach ($subscriptions as $subscription) {
                    if (!$subscription->isBooting()) {
                        continue;
                    }

                    if ($subscription->runMode() === RunMode::Once) {
                        $subscription->finished();
                        $this->subscriptionStore->update($subscription);

                        $this->logger?->info(sprintf(
                            'Subscription Engine: Subscription "%s" run only once and has been set to finished.',
                            $subscription->id(),
                        ));

                        continue;
                    }

                    $subscription->active();
                    $this->subscriptionStore->update($subscription);

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscription "%s" has been set to active after booting.',
                        $subscription->id(),
                    ));
                }

                $this->logger?->info('Subscription Engine: Finish booting.');
            },
        );
    }

    public function run(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): void {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->logger?->info('Subscription Engine: Start processing.');

        $this->discoverNewSubscriptions();
        $this->markOutdatedSubscriptions($criteria);
        $this->retrySubscriptions($criteria);

        $this->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [Status::Active],
            ),
            function (array $subscriptions) use ($limit): void {
                if (count($subscriptions) === 0) {
                    $this->logger?->info('Subscription Engine: No subscriptions to process, finish processing.');

                    return;
                }

                $startIndex = $this->lowestSubscriptionPosition($subscriptions);

                $this->logger?->debug(
                    sprintf(
                        'Subscription Engine: Event stream is processed from position %d.',
                        $startIndex,
                    ),
                );

                $stream = null;
                $messageCounter = 0;

                try {
                    $criteria = new Criteria(fromIndex: $startIndex);
                    $stream = $this->messageStore->load($criteria);

                    foreach ($stream as $message) {
                        $index = $stream->index();

                        if ($index === null) {
                            throw new UnexpectedError('Stream index is null, this should not happen.');
                        }

                        foreach ($subscriptions as $subscription) {
                            if (!$subscription->isActive()) {
                                continue;
                            }

                            if ($subscription->position() >= $index) {
                                $this->logger?->debug(
                                    sprintf(
                                        'Subscription Engine: Subscription "%s" is farther than the current position (%d > %d), continue processing.',
                                        $subscription->id(),
                                        $subscription->position(),
                                        $index,
                                    ),
                                );

                                continue;
                            }

                            $this->handleMessage($index, $message, $subscription);
                        }

                        $messageCounter++;

                        $this->logger?->debug(sprintf('Subscription Engine: Current event stream position: %s', $index));

                        if ($limit !== null && $messageCounter >= $limit) {
                            $this->logger?->info(
                                sprintf(
                                    'Subscription Engine: Message limit (%d) reached, finish processing.',
                                    $limit,
                                ),
                            );

                            return;
                        }
                    }
                } finally {
                    if ($messageCounter > 0) {
                        foreach ($subscriptions as $subscription) {
                            if (!$subscription->isActive()) {
                                continue;
                            }

                            $this->subscriptionStore->update($subscription);
                        }
                    }

                    $stream?->close();
                }

                $this->logger?->info(
                    sprintf(
                        'Subscription Engine: End of stream on position "%d" has been reached, finish processing.',
                        $stream->index() ?: 'unknown',
                    ),
                );
            },
        );
    }

    public function teardown(SubscriptionEngineCriteria|null $criteria = null): void
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->discoverNewSubscriptions();

        $this->logger?->info('Subscription Engine: Start teardown outdated subscriptions.');

        $this->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [Status::Outdated],
            ),
            function (array $subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriber($subscription->id());

                    if (!$subscriber) {
                        $this->logger?->warning(
                            sprintf(
                                'Subscription Engine: Subscriber for "%s" to teardown not found, skipped.',
                                $subscription->id(),
                            ),
                        );

                        continue;
                    }

                    $teardownMethod = $subscriber->teardownMethod();

                    if (!$teardownMethod) {
                        $this->subscriptionStore->remove($subscription);

                        $this->logger?->info(
                            sprintf(
                                'Subscription Engine: Subscriber "%s" for "%s" has no teardown method and was immediately removed.',
                                $subscriber::class,
                                $subscription->id(),
                            ),
                        );

                        continue;
                    }

                    try {
                        $teardownMethod();

                        $this->logger?->debug(sprintf(
                            'Subscription Engine: For Subscriber "%s" for "%s" the teardown method has been executed and is now prepared to be removed.',
                            $subscriber::class,
                            $subscription->id(),
                        ));
                    } catch (Throwable $e) {
                        $this->logger?->error(
                            sprintf(
                                'Subscription Engine: Subscription "%s" for "%s" has an error in the teardown method, skipped: %s',
                                $subscriber::class,
                                $subscription->id(),
                                $e->getMessage(),
                            ),
                        );
                        continue;
                    }

                    $this->subscriptionStore->remove($subscription);

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: Subscription "%s" removed.',
                            $subscription->id(),
                        ),
                    );
                }

                $this->logger?->info('Subscription Engine: Finish teardown.');
            },
        );
    }

    public function remove(SubscriptionEngineCriteria|null $criteria = null): void
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->discoverNewSubscriptions();

        $this->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
            ),
            function (array $subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriber($subscription->id());

                    if (!$subscriber) {
                        $this->subscriptionStore->remove($subscription);

                        $this->logger?->info(
                            sprintf(
                                'Subscription Engine: Subscription "%s" removed without a suitable subscriber.',
                                $subscription->id(),
                            ),
                        );

                        continue;
                    }

                    $teardownMethod = $subscriber->teardownMethod();

                    if (!$teardownMethod) {
                        $this->subscriptionStore->remove($subscription);

                        $this->logger?->info(
                            sprintf('Subscription Engine: Subscription "%s" removed.', $subscription->id()),
                        );

                        continue;
                    }

                    try {
                        $teardownMethod();
                    } catch (Throwable $e) {
                        $this->logger?->error(
                            sprintf(
                                'Subscription Engine: Subscriber "%s" teardown method could not be executed: %s',
                                $subscriber::class,
                                $e->getMessage(),
                            ),
                        );
                    }

                    $this->subscriptionStore->remove($subscription);

                    $this->logger?->info(
                        sprintf('Subscription Engine: Subscription "%s" removed.', $subscription->id()),
                    );
                }
            },
        );
    }

    public function reactivate(SubscriptionEngineCriteria|null $criteria = null): void
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->discoverNewSubscriptions();

        $this->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [
                    Status::Error,
                    Status::Outdated,
                    Status::Paused,
                    Status::Finished,
                ],
            ),
            function (array $subscriptions): void {
                /** @var Subscription $subscription */
                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriber($subscription->id());

                    if (!$subscriber) {
                        $this->logger?->debug(
                            sprintf('Subscription Engine: Subscriber for "%s" not found, skipped.', $subscription->id()),
                        );

                        continue;
                    }

                    $error = $subscription->subscriptionError();

                    if ($error) {
                        $subscription->doRetry();
                        $subscription->resetRetry();

                        $this->subscriptionStore->update($subscription);

                        $this->logger?->info(sprintf(
                            'Subscription Engine: Subscriber "%s" for "%s" is reactivated.',
                            $subscriber::class,
                            $subscription->id(),
                        ));

                        continue;
                    }

                    $subscription->active();
                    $this->subscriptionStore->update($subscription);

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscriber "%s" for "%s" is reactivated.',
                        $subscriber::class,
                        $subscription->id(),
                    ));
                }
            },
        );
    }

    public function pause(SubscriptionEngineCriteria|null $criteria = null): void
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->discoverNewSubscriptions();

        $this->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [
                    Status::Active,
                    Status::Booting,
                    Status::Error,
                ],
            ),
            function (array $subscriptions): void {
                /** @var Subscription $subscription */
                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriber($subscription->id());

                    if (!$subscriber) {
                        $this->logger?->debug(
                            sprintf('Subscription Engine: Subscriber for "%s" not found, skipped.', $subscription->id()),
                        );

                        continue;
                    }

                    $subscription->pause();
                    $this->subscriptionStore->update($subscription);

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscriber "%s" for "%s" is paused.',
                        $subscriber::class,
                        $subscription->id(),
                    ));
                }
            },
        );
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->discoverNewSubscriptions();

        return $this->subscriptionStore->find(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
            ),
        );
    }

    private function handleMessage(int $index, Message $message, Subscription $subscription): void
    {
        $subscriber = $this->subscriber($subscription->id());

        if (!$subscriber) {
            throw SubscriberNotFound::forSubscriptionId($subscription->id());
        }

        $subscribeMethods = $subscriber->subscribeMethods($message->event()::class);

        if ($subscribeMethods === []) {
            $subscription->changePosition($index);

            $this->logger?->debug(
                sprintf(
                    'Subscription Engine: Subscriber "%s" for "%s" has no subscribe methods for "%s", continue.',
                    $subscriber::class,
                    $subscription->id(),
                    $message->event()::class,
                ),
            );

            return;
        }

        try {
            foreach ($subscribeMethods as $subscribeMethod) {
                $subscribeMethod($message);
            }
        } catch (Throwable $e) {
            $this->logger?->error(
                sprintf(
                    'Subscription Engine: Subscriber "%s" for "%s" could not process the event "%s": %s',
                    $subscriber::class,
                    $subscription->id(),
                    $message->event()::class,
                    $e->getMessage(),
                ),
            );

            $this->handleError($subscription, $e);

            return;
        }

        $subscription->changePosition($index);
        $subscription->resetRetry();

        $this->logger?->debug(
            sprintf(
                'Subscription Engine: Subscriber "%s" for "%s" processed the event "%s".',
                $subscriber::class,
                $subscription->id(),
                $message->event()::class,
            ),
        );
    }

    private function subscriber(string $subscriberId): SubscriberAccessor|null
    {
        return $this->subscriberRepository->get($subscriberId);
    }

    private function markOutdatedSubscriptions(SubscriptionEngineCriteria $criteria): void
    {
        $this->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [Status::Active, Status::Paused, Status::Finished],
            ),
            function (array $subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriber($subscription->id());

                    if ($subscriber) {
                        continue;
                    }

                    $subscription->outdated();
                    $this->subscriptionStore->update($subscription);

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: Subscriber for "%s" not found and has been marked as outdated.',
                            $subscription->id(),
                        ),
                    );
                }
            },
        );
    }

    private function retrySubscriptions(SubscriptionEngineCriteria $criteria): void
    {
        $this->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [Status::Error],
            ),
            function (array $subscriptions): void {
                /** @var Subscription $subscription */
                foreach ($subscriptions as $subscription) {
                    $error = $subscription->subscriptionError();

                    if ($error === null) {
                        continue;
                    }

                    $retryable = in_array(
                        $error->previousStatus,
                        [Status::New, Status::Booting, Status::Active],
                        true,
                    );

                    if (!$retryable) {
                        continue;
                    }

                    if (!$this->retryStrategy->shouldRetry($subscription)) {
                        continue;
                    }

                    $subscription->doRetry();
                    $this->subscriptionStore->update($subscription);

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: Retry subscription "%s" (%d) and set back to %s.',
                            $subscription->id(),
                            $subscription->retryAttempt(),
                            $subscription->status()->value,
                        ),
                    );
                }
            },
        );
    }

    /**
     * @param list<Subscription> $subscriptions
     *
     * @return list<Subscription>
     */
    private function fastForwardFromNowSubscriptions(array $subscriptions): array
    {
        $latestIndex = null;
        $forwardedSubscriptions = [];

        foreach ($subscriptions as $subscription) {
            $subscriber = $this->subscriber($subscription->id());

            if (!$subscriber) {
                $forwardedSubscriptions[] = $subscription;

                continue;
            }

            if ($subscription->runMode() === RunMode::FromBeginning || $subscription->runMode() === RunMode::Once) {
                $forwardedSubscriptions[] = $subscription;

                continue;
            }

            if ($latestIndex === null) {
                $latestIndex = $this->latestIndex();
            }

            $subscription->changePosition($latestIndex);
            $subscription->active();
            $this->subscriptionStore->update($subscription);

            $this->logger?->info(
                sprintf(
                    'Subscription Engine: Subscriber "%s" for "%s" is in "from now" mode: skip past messages and set to active.',
                    $subscriber::class,
                    $subscription->id(),
                ),
            );
        }

        return $forwardedSubscriptions;
    }

    private function setupNewSubscriptions(SubscriptionEngineCriteria $criteria): void
    {
        $this->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [Status::New],
            ),
            function (array $subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriber($subscription->id());

                    if (!$subscriber) {
                        throw SubscriberNotFound::forSubscriptionId($subscription->id());
                    }

                    $setupMethod = $subscriber->setupMethod();

                    if (!$setupMethod) {
                        $subscription->booting();
                        $this->subscriptionStore->update($subscription);

                        $this->logger?->debug(sprintf(
                            'Subscription Engine: Subscriber "%s" for "%s" has no setup method, continue.',
                            $subscriber::class,
                            $subscription->id(),
                        ));

                        continue;
                    }

                    try {
                        $setupMethod();

                        $subscription->booting();
                        $this->subscriptionStore->update($subscription);

                        $this->logger?->debug(sprintf(
                            'Subscription Engine: For Subscriber "%s" for "%s" the setup method has been executed and is now prepared for data.',
                            $subscriber::class,
                            $subscription->id(),
                        ));
                    } catch (Throwable $e) {
                        $this->logger?->error(sprintf(
                            'Subscription Engine: Subscriber "%s" for "%s" has an error in the setup method: %s',
                            $subscriber::class,
                            $subscription->id(),
                            $e->getMessage(),
                        ));

                        $this->handleError($subscription, $e);
                    }
                }
            },
        );
    }

    private function discoverNewSubscriptions(): void
    {
        $this->findForUpdate(
            new SubscriptionCriteria(),
            function (array $subscriptions): void {
                foreach ($this->subscriberRepository->all() as $subscriber) {
                    foreach ($subscriptions as $subscription) {
                        if ($subscription->id() === $subscriber->id()) {
                            continue 2;
                        }
                    }

                    $this->subscriptionStore->add(
                        new Subscription(
                            $subscriber->id(),
                            $subscriber->group(),
                            $subscriber->runMode(),
                        ),
                    );

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: New Subscriber "%s" was found and added to the subscription store.',
                            $subscriber->id(),
                        ),
                    );
                }
            },
        );
    }

    private function latestIndex(): int
    {
        $stream = $this->messageStore->load(null, 1, null, true);

        return $stream->index() ?: 0;
    }

    /** @param list<Subscription> $subscriptions */
    private function lowestSubscriptionPosition(array $subscriptions): int
    {
        $min = null;

        foreach ($subscriptions as $subscription) {
            if ($min !== null && $subscription->position() >= $min) {
                continue;
            }

            $min = $subscription->position();
        }

        if ($min === null) {
            return 0;
        }

        return $min;
    }

    /** @param Closure(list<Subscription>):void $closure */
    private function findForUpdate(SubscriptionCriteria $criteria, Closure $closure): void
    {
        if (!$this->subscriptionStore instanceof LockableSubscriptionStore) {
            $closure($this->subscriptionStore->find($criteria));

            return;
        }

        $this->subscriptionStore->inLock(function () use ($closure, $criteria): void {
            $subscriptions = $this->subscriptionStore->find($criteria);

            $closure($subscriptions);
        });
    }

    private function handleError(Subscription $subscription, Throwable $throwable): void
    {
        $subscription->error($throwable);
        $this->subscriptionStore->update($subscription);
    }
}
