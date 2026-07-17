<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:subscription:remove',
    'Delete all subscriptions',
)]
final class SubscriptionRemoveCommand extends SubscriptionCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Set this parameter to execute this action without prompting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        $criteria = $this->subscriptionEngineCriteria($input);
        $force = InputHelper::bool($input->getOption('force'));

        if ($criteria->ids === null && !$force) {
            if (!$io->confirm('do you want to remove all subscriptions?', false)) {
                return 1;
            }
        }

        $this->engine->execute(new Remove($criteria->ids, $criteria->groups));

        return 0;
    }
}
