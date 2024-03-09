<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:subscription:setup',
    'Setup new subscriptions',
)]
final class SubscriptionSetupCommand extends SubscriptionCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = $this->subscriptionEngineCriteria($input);
        $this->engine->setup($criteria);

        return 0;
    }
}
