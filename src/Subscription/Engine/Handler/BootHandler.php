<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnProcessingFinished;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionCollection;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function count;
use function sprintf;

/**
 * @internal
 *
 * @implements Handler<Boot>
 */
final class BootHandler implements Handler
{
    public function __construct(
        private readonly MessageLoader $messageLoader,
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly MessageProcessor $messageProcessor,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): ProcessedResult
    {
        $this->logger?->info(
            'Subscription Engine: Start booting.',
        );

        return $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Booting],
            ),
            function (SubscriptionCollection $subscriptions) use ($command): ProcessedResult {
                if (count($subscriptions) === 0) {
                    $this->logger?->info('Subscription Engine: No subscriptions in booting status, finish booting.');

                    return new ProcessedResult(0, true);
                }

                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriberRepository->get($subscription->id());

                    if ($subscriber) {
                        continue;
                    }

                    $this->logger?->debug(
                        sprintf(
                            'Subscription Engine: Subscriber for "%s" not found, skipped.',
                            $subscription->id(),
                        ),
                    );

                    $subscriptions->remove($subscription);
                }

                $startIndex = $subscriptions->lowestPosition();

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
                $lastIndex = null;

                try {
                    $stream = $this->messageLoader->load($startIndex, $subscriptions->toArray());

                    foreach ($stream as $index => $message) {
                        $messageCounter++;
                        $lastIndex = $index;

                        foreach ($subscriptions as $subscription) {
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

                            $error = $this->messageProcessor->process($index, $message, $subscription);

                            if (!$error) {
                                continue;
                            }

                            $errors[] = $error;

                            $subscriptions->remove($subscription);

                            if (count($subscriptions) === 0) {
                                $this->logger?->info(
                                    'Subscription Engine: No subscriptions in booting status, finish booting.',
                                );

                                break 2;
                            }
                        }

                        $this->logger?->debug(
                            sprintf(
                                'Subscription Engine: Current event stream position for booting: %s',
                                $index,
                            ),
                        );

                        if ($command->limit !== null && $messageCounter >= $command->limit) {
                            $this->logger?->info(
                                sprintf(
                                    'Subscription Engine: Message limit (%d) reached, finish booting.',
                                    $command->limit,
                                ),
                            );

                            $limitEvent = new OnProcessingFinished(
                                $command,
                                OnProcessingFinished::REASON_LIMIT_REACHED,
                                $messageCounter,
                                $lastIndex,
                            );
                            $this->eventDispatcher->dispatch($limitEvent);
                            $errors = array_merge($errors, $limitEvent->errors);

                            return new ProcessedResult(
                                $messageCounter,
                                false,
                                $errors,
                            );
                        }
                    }

                    $finishedEvent = new OnProcessingFinished(
                        $command,
                        OnProcessingFinished::REASON_STREAM_ENDED,
                        $messageCounter,
                        $lastIndex,
                    );
                    $this->eventDispatcher->dispatch($finishedEvent);
                    $errors = array_merge($errors, $finishedEvent->errors);
                } finally {
                    $stream?->close();

                    if ($lastIndex !== null && $messageCounter > 0) {
                        foreach ($subscriptions as $subscription) {
                            $this->subscriptionManager->update($subscription);
                        }
                    }
                }

                $this->logger?->debug('Subscription Engine: End of stream for booting has been reached.');

                foreach ($subscriptions as $subscription) {
                    if ($subscription->status() !== Status::Booting) {
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
    }
}
