<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionProcessed;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_merge;
use function count;
use function sprintf;

/** @internal */
final class SubscriptionRunner
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

    /** @param list<Subscription> $subscriptions */
    public function process(array $subscriptions, int|null $limit, bool $boot = false): ProcessedResult
    {
        $surviving = [];

        foreach ($subscriptions as $subscription) {
            if ($this->subscriberRepository->get($subscription->subscriberId()) === null) {
                $this->logger?->debug(sprintf(
                    'Subscription Engine: Subscriber for "%s" not found, skipped.',
                    $subscription->id(),
                ));

                continue;
            }

            $surviving[] = $subscription;
        }

        $members = new SubscriptionCollection($surviving);

        if (count($members) === 0) {
            return ProcessedResult::empty();
        }

        $startIndex = $members->lowestPosition();

        $this->logger?->debug(sprintf(
            'Subscription Engine: Event stream is processed from position %s.',
            $startIndex ?? 'beginning',
        ));

        /** @var list<Error> $errors */
        $errors = [];
        $stream = null;
        $messageCounter = 0;
        $lastIndex = null;
        $limitReached = false;

        try {
            $stream = $this->messageLoader->load($startIndex, $members->toArray());

            foreach ($stream as $index => $message) {
                $messageCounter++;
                $lastIndex = $index;

                foreach ($members as $member) {
                    if ($member->position() >= $index) {
                        continue;
                    }

                    $error = $this->messageProcessor->process($index, $message, $member);

                    if (!$error) {
                        continue;
                    }

                    $errors[] = $error;
                    $members->remove($member);

                    if (count($members) === 0) {
                        break 2;
                    }
                }

                if ($limit !== null && $messageCounter >= $limit) {
                    $this->logger?->info(sprintf(
                        'Subscription Engine: Message limit (%d) reached, finish processing.',
                        $limit,
                    ));

                    $limitReached = true;

                    break;
                }
            }
        } finally {
            $stream?->close();
        }

        if ($lastIndex !== null) {
            foreach ($members as $member) {
                $event = new OnSubscriptionProcessed($member, $lastIndex);
                $this->eventDispatcher->dispatch($event);
                $errors = array_merge($errors, $event->errors);
            }
        }

        if ($lastIndex !== null) {
            foreach ($members as $member) {
                $this->subscriptionManager->update($member);
            }
        }

        if (!$limitReached) {
            $this->finishMembers($members, $boot);
        }

        return new ProcessedResult($messageCounter, !$limitReached, $errors);
    }

    private function finishMembers(SubscriptionCollection $members, bool $boot): void
    {
        foreach ($members as $member) {
            if ($boot) {
                if ($member->status() !== Status::Booting) {
                    continue;
                }

                if ($member->runMode() === RunMode::Once) {
                    $member->finished();

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscription "%s" run only once and has been set to finished.',
                        $member->id(),
                    ));
                } else {
                    $member->active();

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscription "%s" has been set to active after booting.',
                        $member->id(),
                    ));
                }

                $this->subscriptionManager->update($member);

                continue;
            }

            if ($member->status() !== Status::Active || $member->runMode() !== RunMode::Once) {
                continue;
            }

            $member->finished();
            $this->subscriptionManager->update($member);

            $this->logger?->info(sprintf(
                'Subscription Engine: Subscription "%s" run only once and has been set to finished.',
                $member->id(),
            ));
        }
    }
}
