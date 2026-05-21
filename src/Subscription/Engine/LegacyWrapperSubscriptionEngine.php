<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Cleanup\Cleaner;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Reactivate;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Refresh;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Teardown;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;

final class LegacyWrapperSubscriptionEngine implements SubscriptionEngine, CanRefreshSubscriptions
{
    private readonly NextSubscriptionEngine $engine;

    public function __construct(
        MessageLoader $messageLoader,
        SubscriptionStore $subscriptionStore,
        SubscriberAccessorRepository $subscriberRepository,
        RetryStrategyRepository|null $retryStrategyRepository = null,
        LoggerInterface|null $logger = null,
        Cleaner|null $cleaner = null,
    ) {
        $this->engine = new NextSubscriptionEngine(
            $messageLoader,
            $subscriptionStore,
            $subscriberRepository,
            $retryStrategyRepository,
            $logger,
            $cleaner,
        );
    }

    public function setup(SubscriptionEngineCriteria|null $criteria = null, bool $skipBooting = false): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        return $this->engine->run(new Setup(
            $criteria->ids,
            $criteria->groups,
            $skipBooting,
        ));
    }

    public function boot(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): ProcessedResult {
        $criteria ??= new SubscriptionEngineCriteria();

        return $this->engine->run(new Boot(
            $criteria->ids,
            $criteria->groups,
            $limit,
        ));
    }

    public function run(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): ProcessedResult {
        $criteria ??= new SubscriptionEngineCriteria();

        return $this->engine->run(new Run(
            $criteria->ids,
            $criteria->groups,
            $limit,
        ));
    }

    public function teardown(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        return $this->engine->run(new Teardown(
            $criteria->ids,
            $criteria->groups,
        ));
    }

    public function remove(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        return $this->engine->run(new Remove(
            $criteria->ids,
            $criteria->groups,
        ));
    }

    public function reactivate(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        return $this->engine->run(new Reactivate(
            $criteria->ids,
            $criteria->groups,
        ));
    }

    public function pause(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        return $this->engine->run(new Pause(
            $criteria->ids,
            $criteria->groups,
        ));
    }

    public function refresh(SubscriptionEngineCriteria|null $criteria = null): Result
    {
        $criteria ??= new SubscriptionEngineCriteria();

        return $this->engine->run(new Refresh(
            $criteria->ids,
            $criteria->groups,
        ));
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        return $this->engine->subscriptions($criteria);
    }
}
