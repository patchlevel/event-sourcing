<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Refresh;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:subscription:refresh',
    'Refresh subscriptions (run-mode, group)',
)]
final class SubscriptionRefreshCommand extends SubscriptionCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = $this->subscriptionEngineCriteria($input);
        $this->engine->run(new Refresh($criteria->ids, $criteria->groups));

        return 0;
    }
}
