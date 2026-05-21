<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Subscription\Cleanup\Cleaner;
use Patchlevel\EventSourcing\Subscription\Engine\CleanupRunner;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Reactivate;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Refresh;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Teardown;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnResult;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptions;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\BootHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\Handler;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\PauseHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\ReactivateHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\RefreshHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\RemoveHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\RunHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\SetupHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\TeardownHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\BatchSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\DetachListener;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\DiscoverListener;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\FailListener;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\RetrySubscriber;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class NextSubscriptionEngine
{
    private SubscriptionManager $subscriptionManager;

    private bool $processing = false;

    private readonly RetryStrategyRepository $retryStrategyRepository;

    /** @var array<class-string<Command>, Handler> */
    private readonly array $handlers;

    public function __construct(
        private readonly MessageLoader $messageLoader,
        SubscriptionStore $subscriptionStore,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        RetryStrategyRepository|null $retryStrategyRepository = null,
        private readonly LoggerInterface|null $logger = null,
        private readonly Cleaner|null $cleaner = null,
        private readonly EventDispatcherInterface $eventDispatcher = new EventDispatcher(),
    ) {
        $this->subscriptionManager = new SubscriptionManager($subscriptionStore);

        if ($retryStrategyRepository instanceof RetryStrategyRepository) {
            $this->retryStrategyRepository = $retryStrategyRepository;
        } else {
            $this->retryStrategyRepository = new RetryStrategyRepository([
                RetryStrategyRepository::DEFAULT_STRATEGY_NAME => new ClockBasedRetryStrategy(),
                'no_retry' => new NoRetryStrategy(),
            ]);
        }

        $cleanupRunner = new CleanupRunner(
            $this->subscriptionManager,
            $this->cleaner,
            $this->logger,
        );

        $messageProcessor = new MessageProcessor(
            $this->subscriberRepository,
            $this->eventDispatcher,
            $this->logger,
        );

        $this->handlers = [
            Boot::class => new BootHandler(
                $this->messageLoader,
                $this->subscriptionManager,
                $this->subscriberRepository,
                $messageProcessor,
                $this->eventDispatcher,
                $this->logger,
            ),
            Pause::class => new PauseHandler(
                $this->subscriptionManager,
                $this->logger,
            ),
            Reactivate::class => new ReactivateHandler(
                $this->subscriptionManager,
                $this->subscriberRepository,
                $this->logger,
            ),
            Refresh::class => new RefreshHandler(
                $this->subscriptionManager,
                $this->subscriberRepository,
                $this->logger,
            ),
            Remove::class => new RemoveHandler(
                $this->subscriptionManager,
                $this->subscriberRepository,
                $cleanupRunner,
                $this->logger,
            ),
            Run::class => new RunHandler(
                $this->messageLoader,
                $this->subscriptionManager,
                $this->subscriberRepository,
                $messageProcessor,
                $this->eventDispatcher,
                $this->logger,
            ),
            Setup::class => new SetupHandler(
                $this->messageLoader,
                $this->subscriptionManager,
                $this->subscriberRepository,
                $this->retryStrategyRepository,
                $this->logger,
            ),
            Teardown::class => new TeardownHandler(
                $this->subscriptionManager,
                $this->subscriberRepository,
                $cleanupRunner,
                $this->logger,
            ),
        ];

        $this->eventDispatcher->addSubscriber(
            new DiscoverListener(
                $this->messageLoader,
                $this->subscriptionManager,
                $this->subscriberRepository,
                $this->logger,
            ),
        );

        $this->eventDispatcher->addSubscriber(
            new RetrySubscriber(
                $this->subscriptionManager,
                $this->subscriberRepository,
                $this->retryStrategyRepository,
                $this->logger,
            ),
        );

        $this->eventDispatcher->addSubscriber(
            new BatchSubscriber(
                $this->subscriberRepository,
                $this->logger,
            ),
        );

        $this->eventDispatcher->addSubscriber(
            new FailListener(
                $this->subscriptionManager,
                $this->subscriberRepository,
                $this->logger,
            ),
        );

        $this->eventDispatcher->addListener(
            OnCommand::class,
            new DetachListener(
                $this->subscriptionManager,
                $this->subscriberRepository,
                $this->logger,
            ),
            32,
        );
    }

    public function run(Command $command): Result
    {
        if ($this->processing) {
            throw new AlreadyProcessing();
        }

        $this->processing = true;

        try {
            $handler = $this->handlers[$command::class] ?? null;

            if ($handler === null) {
                throw new InvalidArgumentException('No handler found for command: ' . $command::class);
            }

            $event = new OnCommand($command);
            $this->eventDispatcher->dispatch($event);

            $result = $handler($command);

            $event = new OnResult($command, $result);
            $this->eventDispatcher->dispatch($event);

            return $result;
        } finally {
            $this->processing = false;
        }
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        $criteria ??= new SubscriptionEngineCriteria();

        $this->eventDispatcher->dispatch(new OnSubscriptions($criteria));

        return $this->subscriptionManager->find(
            new SubscriptionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
            ),
        );
    }
}
