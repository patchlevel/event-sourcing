<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnProcessingFinished;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionCollection;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_merge;
use function count;
use function sprintf;

/**
 * @internal
 *
 * @implements Handler<Run>
 */
final class RunHandler implements Handler
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
        return $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Active],
            ),
            function (SubscriptionCollection $subscriptions) use ($command): ProcessedResult {
                if (count($subscriptions) === 0) {
                    $this->logger?->info('Subscription Engine: No subscriptions to process, finish processing.');

                    return new ProcessedResult(0, true);
                }

                $startIndex = $subscriptions->lowestPosition();

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
                                        'Subscription Engine: Subscription "%s" is farther than the current position (%d > %d), continue processing.',
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
                                    'Subscription Engine: No subscriptions in active status, finish processing.',
                                );

                                break 2;
                            }
                        }

                        $this->logger?->debug(sprintf(
                            'Subscription Engine: Current event stream position: %s',
                            $index,
                        ));

                        if ($command->limit !== null && $messageCounter >= $command->limit) {
                            $this->logger?->info(
                                sprintf(
                                    'Subscription Engine: Message limit (%d) reached, finish processing.',
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

                            return new ProcessedResult($messageCounter, false, $errors);
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

                foreach ($subscriptions as $subscription) {
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
                        $lastIndex,
                    ),
                );

                return new ProcessedResult($messageCounter, true, $errors);
            },
        );
    }
}
