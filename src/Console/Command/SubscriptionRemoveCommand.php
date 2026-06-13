<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:subscription:remove',
    'Delete all subscriptions',
)]
final class SubscriptionRemoveCommand extends SubscriptionCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        $criteria = $this->subscriptionEngineCriteria($input);

        if ($criteria->ids === null) {
            if (!$io->confirm('do you want to remove all subscriptions?', false)) {
                return 1;
            }
        }

        $this->engine->execute(new Remove($criteria->ids, $criteria->groups));

        return 0;
    }
}
