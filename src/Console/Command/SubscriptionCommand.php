<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

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
}
