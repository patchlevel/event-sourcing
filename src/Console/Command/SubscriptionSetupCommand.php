<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:subscription:setup',
    'Setup new subscriptions',
)]
final class SubscriptionSetupCommand extends SubscriptionCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                'skip-booting',
                null,
                InputOption::VALUE_NONE,
                'Skip booting',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $skipBooting = InputHelper::bool($input->getOption('skip-booting'));

        $criteria = $this->subscriptionEngineCriteria($input);
        $this->engine->setup($criteria, $skipBooting);

        return 0;
    }
}
