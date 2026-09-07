<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Subscription\Cleanup\Cleaner;
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
use Patchlevel\EventSourcing\Subscription\Engine\Listener\DiscoverSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\FailSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\RetrySubscriber;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\BatchArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchManager;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class DefaultSubscriptionEngine implements SubscriptionEngine
{
    private SubscriptionManager $subscriptionManager;

    private bool $processing = false;

    private readonly RetryStrategyRepository $retryStrategyRepository;

    /** @var array<class-string<Command>, Handler> */
    private readonly array $handlers;

    /** @param iterable<ArgumentResolver> $argumentResolvers */
    public function __construct(
        private readonly MessageLoader $messageLoader,
        SubscriptionStore $subscriptionStore,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        RetryStrategyRepository|null $retryStrategyRepository = null,
        private readonly LoggerInterface|null $logger = null,
        private readonly Cleaner|null $cleaner = null,
        private readonly EventDispatcherInterface $eventDispatcher = new EventDispatcher(),
        iterable $argumentResolvers = [],
    ) {
        $this->subscriptionManager = new SubscriptionManager($subscriptionStore, $this->logger);

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

        $batchManager = new BatchManager();

        $messageProcessor = new MessageProcessor(
            $this->subscriberRepository,
            $this->eventDispatcher,
            [new BatchArgumentResolver($batchManager), ...$argumentResolvers],
            $this->logger,
        );

        $runner = new SubscriptionRunner(
            $this->messageLoader,
            $this->subscriptionManager,
            $this->subscriberRepository,
            $messageProcessor,
            $this->eventDispatcher,
            $this->logger,
        );

        $this->handlers = [
            Boot::class => new BootHandler(
                $this->subscriptionManager,
                $runner,
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
                $this->eventDispatcher,
                $this->logger,
            ),
            Run::class => new RunHandler(
                $this->subscriptionManager,
                $runner,
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
                $this->eventDispatcher,
                $this->logger,
            ),
        ];

        $this->eventDispatcher->addSubscriber(
            new DiscoverSubscriber(
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
                $batchManager,
                $this->subscriberRepository,
                $this->logger,
            ),
        );

        $this->eventDispatcher->addSubscriber(
            new FailSubscriber(
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

    public function execute(Command $command): Result
    {
        $this->logger?->info(
            'Subscription Engine: ' . $command::class . ' command received.',
        );

        if ($this->processing) {
            $this->logger?->error(
                'Subscription Engine: Already processing, skip.',
            );

            throw new AlreadyProcessing();
        }

        $this->processing = true;

        try {
            $handler = $this->handlers[$command::class] ?? null;

            if ($handler === null) {
                throw new InvalidArgumentException('No handler found for command: ' . $command::class);
            }

            $this->logger?->debug(
                'Subscription Engine: ' . $command::class . ' command handled by ' . $handler::class,
            );

            $event = new OnCommand($command);
            $this->eventDispatcher->dispatch($event);

            $result = $handler($command);

            $event = new OnResult($command, $result);
            $this->eventDispatcher->dispatch($event);

            return $result;
        } finally {
            $this->logger?->info(
                'Subscription Engine: ' . $command::class . ' command processed.',
            );

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
