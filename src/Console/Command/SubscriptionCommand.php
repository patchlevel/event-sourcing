<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use function array_map;
use function array_unique;
use function array_values;

/** @interal */
abstract class SubscriptionCommand extends Command
{
    public function __construct(
        protected readonly SubscriptionEngine $engine,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'id',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'filter by subscription id',
            )
            ->addOption(
                'group',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'filter by subscription group',
            );
    }

    protected function subscriptionEngineCriteria(InputInterface $input): SubscriptionEngineCriteria
    {
        return new SubscriptionEngineCriteria(
            InputHelper::nullableStringList($input->getOption('id')),
            InputHelper::nullableStringList($input->getOption('group')),
        );
    }

    protected function resolveCriteriaIntoCriteriaWithOnlyIds(
        SubscriptionEngineCriteria $criteria,
    ): SubscriptionEngineCriteria {
        $subscriptions = $this->engine->subscriptions($criteria);

        // a subscriber id includes all partitions, so the list stays as short as the number of subscribers
        return new SubscriptionEngineCriteria(
            array_values(array_unique(array_map(
                static fn ($subscription) => $subscription->subscriberId(),
                $subscriptions,
            ))),
        );
    }
}
