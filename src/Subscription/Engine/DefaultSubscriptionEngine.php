<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;
use Patchlevel\EventSourcing\Subscription\Subscriber\RealSubscriberAccessor;
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
    private SubscriptionManager $subscriptionManager;

    private bool $processing = false;

    /** @var array<string, BatchableSubscriber> */
    private array $batching = [];

    public function __construct(
        private readonly Store $messageStore,
        SubscriptionStore $subscriptionStore,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly RetryStrategy $retryStrategy = new ClockBasedRetryStrategy(),
        private readonly LoggerInterface|null $logger = null,
    ) {
        $this->subscriptionManager = new SubscriptionManager($subscriptionStore);
    }

    public function setup(SubscriptionEngineCriteria|null $criteria = null, bool $skipBooting = false): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->logger?->info(
            'Subscription Engine: Start to setup.',
        );

        $this->discoverNewSubscriptions();
        $this->retrySubscriptions($criteria);

        return $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [Status::New],
            ),
            function (array $subscriptions) use ($skipBooting): Result {
                if (count($subscriptions) === 0) {
                    $this->logger?->info('Subscription Engine: No subscriptions to setup, finish setup.');

                    return new Result();
                }

                /** @var list<Error> $errors */
                $errors = [];

                $latestIndex = $this->latestIndex();

                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriber($subscription->id());

                    if (!$subscriber) {
                        throw SubscriberNotFound::forSubscriptionId($subscription->id());
                    }

                    $setupMethod = $subscriber->setupMethod();

                    if (!$setupMethod) {
                        if ($subscription->runMode() === RunMode::FromNow) {
                            $subscription->changePosition($latestIndex);
                            $subscription->active();
                        } else {
                            $skipBooting ? $subscription->active() : $subscription->booting();
                        }

                        $this->subscriptionManager->update($subscription);

                        $this->logger?->debug(sprintf(
                            'Subscription Engine: Subscriber "%s" for "%s" has no setup method, set to %s.',
                            $subscriber::class,
                            $subscription->id(),
                            $subscription->runMode() === RunMode::FromNow || $skipBooting ? 'active' : 'booting',
                        ));

                        continue;
                    }

                    try {
                        $setupMethod();

                        if ($subscription->runMode() === RunMode::FromNow) {
                            $subscription->changePosition($latestIndex);
                            $subscription->active();
                        } else {
                            $skipBooting ? $subscription->active() : $subscription->booting();
                        }

                        $this->subscriptionManager->update($subscription);

                        $this->logger?->debug(sprintf(
                            'Subscription Engine: For Subscriber "%s" for "%s" the setup method has been executed, set to %s.',
                            $subscriber::class,
                            $subscription->id(),
                            $subscription->runMode() === RunMode::FromNow || $skipBooting ? 'active' : 'booting',
                        ));
                    } catch (Throwable $e) {
                        $this->logger?->error(sprintf(
                            'Subscription Engine: Subscriber "%s" for "%s" has an error in the setup method: %s',
                            $subscriber::class,
                            $subscription->id(),
                            $e->getMessage(),
                        ));

                        $this->handleError($subscription, $e);

                        $errors[] = new Error(
                            $subscription->id(),
                            $e->getMessage(),
                            $e,
                        );
                    }
                }

                return new Result($errors);
            },
        );
    }

    public function boot(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): ProcessedResult {
        if ($this->processing) {
            throw new AlreadyProcessing();
        }

        $this->processing = true;
        $this->batching = [];

        try {
            $criteria ??= new SubscriptionEngineCriteria();

            $this->logger?->info(
                'Subscription Engine: Start booting.',
            );

            $this->discoverNewSubscriptions();
            $this->retrySubscriptions($criteria);

            return $this->subscriptionManager->findForUpdate(
                new SubscriptionCriteria(
                    ids: $criteria->ids,
                    groups: $criteria->groups,
                    status: [Status::Booting],
                ),
                function ($subscriptions) use ($limit): ProcessedResult {
                    if (count($subscriptions) === 0) {
                        $this->logger?->info('Subscription Engine: No subscriptions in booting status, finish booting.');

                        return new ProcessedResult(0, true);
                    }

                    $startIndex = $this->lowestSubscriptionPosition($subscriptions);

                    $this->logger?->debug(
                        sprintf(
                            'Subscription Engine: Event stream is processed for booting from position %s.',
                            $startIndex,
                        ),
                    );

                    /** @var list<Error> $errors */
                    $errors = [];
                    $stream = null;
                    $messageCounter = 0;

                    try {
                        $stream = $this->messageStore->load(
                            new Criteria(new FromIndexCriterion($startIndex)),
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

                                $error = $this->handleMessage($index, $message, $subscription);

                                if (!$error) {
                                    continue;
                                }

                                $errors[] = $error;
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

                                return new ProcessedResult(
                                    $messageCounter,
                                    false,
                                    $errors,
                                );
                            }
                        }
                    } finally {
                        $endIndex = $stream?->index() ?: $startIndex;
                        $stream?->close();

                        if ($messageCounter > 0) {
                            foreach ($subscriptions as $subscription) {
                                $error = $this->ensureCommitBatch($subscription, $endIndex);

                                if ($error) {
                                    $errors[] = $error;
                                }

                                $this->subscriptionManager->update($subscription);
                            }
                        }
                    }

                    $this->logger?->debug('Subscription Engine: End of stream for booting has been reached.');

                    foreach ($subscriptions as $subscription) {
                        if (!$subscription->isBooting()) {
                            continue;
                        }

                        if ($subscription->runMode() === RunMode::Once) {
                            $subscription->finished();
                            $this->subscriptionManager->update($subscription);

                            $this->logger?->info(sprintf(
                                'Subscription Engine: Subscription "%s" run only once and has been set to finished.',
                                $subscription->id(),
                            ));

                            continue;
                        }

                        $subscription->active();
                        $this->subscriptionManager->update($subscription);

                        $this->logger?->info(sprintf(
                            'Subscription Engine: Subscription "%s" has been set to active after booting.',
                            $subscription->id(),
                        ));
                    }

                    $this->logger?->info('Subscription Engine: Finish booting.');

                    return new ProcessedResult(
                        $messageCounter,
                        true,
                        $errors,
                    );
                },
            );
        } finally {
            $this->processing = false;
        }
    }

    public function run(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): ProcessedResult {
        if ($this->processing) {
            throw new AlreadyProcessing();
        }

        $this->processing = true;
        $this->batching = [];

        try {
            $criteria ??= new SubscriptionEngineCriteria();

            $this->logger?->info('Subscription Engine: Start processing.');

            $this->discoverNewSubscriptions();
            $this->markDetachedSubscriptions($criteria);
            $this->retrySubscriptions($criteria);

            return $this->subscriptionManager->findForUpdate(
                new SubscriptionCriteria(
                    ids: $criteria->ids,
                    groups: $criteria->groups,
                    status: [Status::Active],
                ),
                function (array $subscriptions) use ($limit): ProcessedResult {
                    if (count($subscriptions) === 0) {
                        $this->logger?->info('Subscription Engine: No subscriptions to process, finish processing.');

                        return new ProcessedResult(0, true);
                    }

                    $startIndex = $this->lowestSubscriptionPosition($subscriptions);

                    $this->logger?->debug(
                        sprintf(
                            'Subscription Engine: Event stream is processed from position %d.',
                            $startIndex,
                        ),
                    );

                    /** @var list<Error> $errors */
                    $errors = [];
                    $stream = null;
                    $messageCounter = 0;

                    try {
                        $criteria = new Criteria(new FromIndexCriterion($startIndex));
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

                                $error = $this->handleMessage($index, $message, $subscription);

                                if (!$error) {
                                    continue;
                                }

                                $errors[] = $error;
                            }

                            $messageCounter++;

                            $this->logger?->debug(sprintf(
                                'Subscription Engine: Current event stream position: %s',
                                $index,
                            ));

                            if ($limit !== null && $messageCounter >= $limit) {
                                $this->logger?->info(
                                    sprintf(
                                        'Subscription Engine: Message limit (%d) reached, finish processing.',
                                        $limit,
                                    ),
                                );

                                return new ProcessedResult($messageCounter, false, $errors);
                            }
                        }
                    } finally {
                        $endIndex = $stream?->index() ?: $startIndex;
                        $stream?->close();

                        if ($messageCounter > 0) {
                            foreach ($subscriptions as $subscription) {
                                $error = $this->ensureCommitBatch($subscription, $endIndex);

                                if ($error) {
                                    $errors[] = $error;
                                }

                                $this->subscriptionManager->update($subscription);
                            }
                        }
                    }

                    foreach ($subscriptions as $subscription) {
                        if (!$subscription->isActive()) {
                            continue;
                        }

                        if ($subscription->runMode() !== RunMode::Once) {
                            continue;
                        }

                        $subscription->finished();
                        $this->subscriptionManager->update($subscription);

                        $this->logger?->info(sprintf(
                            'Subscription Engine: Subscription "%s" run only once and has been set to finished.',
                            $subscription->id(),
                        ));
                    }

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: End of stream on position "%d" has been reached, finish processing.',
                            $endIndex,
                        ),
                    );

                    return new ProcessedResult($messageCounter, true, $errors);
                },
            );
        } finally {
            $this->processing = false;
        }
    }

    public function teardown(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->discoverNewSubscriptions();

        $this->logger?->info('Subscription Engine: Start teardown detached subscriptions.');

        return $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [Status::Detached],
            ),
            function (array $subscriptions): Result {
                /** @var list<Error> $errors */
                $errors = [];

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
                        $this->subscriptionManager->remove($subscription);

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

                        $errors[] = new Error(
                            $subscription->id(),
                            $e->getMessage(),
                            $e,
                        );

                        continue;
                    }

                    $this->subscriptionManager->remove($subscription);

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: Subscription "%s" removed.',
                            $subscription->id(),
                        ),
                    );
                }

                $this->logger?->info('Subscription Engine: Finish teardown.');

                return new Result($errors);
            },
        );
    }

    public function remove(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->discoverNewSubscriptions();

        return $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
            ),
            function (array $subscriptions): Result {
                /** @var list<Error> $errors */
                $errors = [];

                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriber($subscription->id());

                    if (!$subscriber) {
                        $this->subscriptionManager->remove($subscription);

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
                        $this->subscriptionManager->remove($subscription);

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

                        $errors[] = new Error(
                            $subscription->id(),
                            $e->getMessage(),
                            $e,
                        );
                    }

                    $this->subscriptionManager->remove($subscription);

                    $this->logger?->info(
                        sprintf('Subscription Engine: Subscription "%s" removed.', $subscription->id()),
                    );
                }

                return new Result($errors);
            },
        );
    }

    public function reactivate(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->discoverNewSubscriptions();

        return $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [
                    Status::Error,
                    Status::Detached,
                    Status::Paused,
                    Status::Finished,
                ],
            ),
            function (array $subscriptions): Result {
                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriber($subscription->id());

                    if (!$subscriber) {
                        $this->logger?->debug(
                            sprintf(
                                'Subscription Engine: Subscriber for "%s" not found, skipped.',
                                $subscription->id(),
                            ),
                        );

                        continue;
                    }

                    $error = $subscription->subscriptionError();

                    if ($error) {
                        $subscription->doRetry();
                        $subscription->resetRetry();

                        $this->subscriptionManager->update($subscription);

                        $this->logger?->info(sprintf(
                            'Subscription Engine: Subscriber "%s" for "%s" is reactivated.',
                            $subscriber::class,
                            $subscription->id(),
                        ));

                        continue;
                    }

                    $subscription->active();
                    $this->subscriptionManager->update($subscription);

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscriber "%s" for "%s" is reactivated.',
                        $subscriber::class,
                        $subscription->id(),
                    ));
                }

                return new Result();
            },
        );
    }

    public function pause(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->discoverNewSubscriptions();

        return $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [
                    Status::Active,
                    Status::Booting,
                    Status::Error,
                ],
            ),
            function (array $subscriptions): Result {
                /** @var Subscription $subscription */
                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriber($subscription->id());

                    if (!$subscriber) {
                        $this->logger?->debug(
                            sprintf(
                                'Subscription Engine: Subscriber for "%s" not found, skipped.',
                                $subscription->id(),
                            ),
                        );

                        continue;
                    }

                    $subscription->pause();
                    $this->subscriptionManager->update($subscription);

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscriber "%s" for "%s" is paused.',
                        $subscriber::class,
                        $subscription->id(),
                    ));
                }

                return new Result();
            },
        );
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->discoverNewSubscriptions();

        return $this->subscriptionManager->find(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
            ),
        );
    }

    private function handleMessage(int $index, Message $message, Subscription $subscription): Error|null
    {
        $subscriber = $this->subscriber($subscription->id());

        if (!$subscriber) {
            throw SubscriberNotFound::forSubscriptionId($subscription->id());
        }

        $subscribeMethods = $subscriber->subscribeMethods($message->event()::class);

        if ($subscribeMethods === []) {
            if (!isset($this->batching[$subscription->id()])) {
                $subscription->changePosition($index);
            }

            $this->logger?->debug(
                sprintf(
                    'Subscription Engine: Subscriber "%s" for "%s" has no subscribe methods for "%s", continue.',
                    $subscriber::class,
                    $subscription->id(),
                    $message->event()::class,
                ),
            );

            return null;
        }

        $error = $this->checkAndBeginBatch($subscription);

        if ($error) {
            return $error;
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

            return new Error(
                $subscription->id(),
                $e->getMessage(),
                $e,
            );
        }

        if ($this->shouldCommitBatch($subscription)) {
            $this->ensureCommitBatch($subscription, $index);
        }

        if (!isset($this->batching[$subscription->id()])) {
            $subscription->changePosition($index);
        }

        $subscription->resetRetry();

        $this->logger?->debug(
            sprintf(
                'Subscription Engine: Subscriber "%s" for "%s" processed the event "%s".',
                $subscriber::class,
                $subscription->id(),
                $message->event()::class,
            ),
        );

        return null;
    }

    private function subscriber(string $subscriberId): SubscriberAccessor|null
    {
        return $this->subscriberRepository->get($subscriberId);
    }

    private function markDetachedSubscriptions(SubscriptionEngineCriteria $criteria): void
    {
        $this->subscriptionManager->findForUpdate(
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

                    $subscription->detached();
                    $this->subscriptionManager->update($subscription);

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: Subscriber for "%s" not found and has been marked as detached.',
                            $subscription->id(),
                        ),
                    );
                }
            },
        );
    }

    private function retrySubscriptions(SubscriptionEngineCriteria $criteria): void
    {
        $this->subscriptionManager->findForUpdate(
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
                    $this->subscriptionManager->update($subscription);

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

    private function discoverNewSubscriptions(): void
    {
        $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(),
            function (array $subscriptions): void {
                $latestIndex = null;

                foreach ($this->subscriberRepository->all() as $subscriber) {
                    foreach ($subscriptions as $subscription) {
                        if ($subscription->id() === $subscriber->id()) {
                            continue 2;
                        }
                    }

                    $subscription = new Subscription(
                        $subscriber->id(),
                        $subscriber->group(),
                        $subscriber->runMode(),
                    );

                    if ($subscriber->setupMethod() === null && $subscriber->runMode() === RunMode::FromNow) {
                        if ($latestIndex === null) {
                            $latestIndex = $this->latestIndex();
                        }

                        $subscription->changePosition($latestIndex);
                        $subscription->active();
                    }

                    $this->subscriptionManager->add($subscription);

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

    private function handleError(Subscription $subscription, Throwable $throwable): void
    {
        $subscription->error($throwable);
        $this->subscriptionManager->update($subscription);

        if (!isset($this->batching[$subscription->id()])) {
            return;
        }

        $subscriber = $this->batching[$subscription->id()];

        unset($this->batching[$subscription->id()]);

        try {
            $subscriber->rollbackBatch();
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the rollback batch method: %s',
                $subscription->id(),
                $e->getMessage(),
            ));
        }
    }

    private function ensureCommitBatch(Subscription $subscription, int $index): Error|null
    {
        if (!isset($this->batching[$subscription->id()])) {
            return null;
        }

        try {
            $this->batching[$subscription->id()]->commitBatch();
            $subscription->changePosition($index);
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the commit batch method: %s',
                $subscription->id(),
                $e->getMessage(),
            ));

            $this->handleError($subscription, $e);

            return new Error(
                $subscription->id(),
                $e->getMessage(),
                $e,
            );
        }

        return null;
    }

    private function checkAndBeginBatch(Subscription $subscription): Error|null
    {
        if (isset($this->batching[$subscription->id()])) {
            return null;
        }

        $subscriber = $this->subscriber($subscription->id());

        if (!$subscriber instanceof RealSubscriberAccessor) {
            return null;
        }

        $realSubscriber = $subscriber->realSubscriber();

        if (!$realSubscriber instanceof BatchableSubscriber) {
            return null;
        }

        $this->batching[$subscription->id()] = $realSubscriber;

        try {
            $realSubscriber->beginBatch();
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the begin batch method: %s',
                $subscription->id(),
                $e->getMessage(),
            ));

            $this->handleError($subscription, $e);

            return new Error(
                $subscription->id(),
                $e->getMessage(),
                $e,
            );
        }

        return null;
    }

    private function shouldCommitBatch(Subscription $subscription): bool
    {
        if (!isset($this->batching[$subscription->id()])) {
            return false;
        }

        return $this->batching[$subscription->id()]->forceCommit();
    }
}
