<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:subscription:teardown',
    'Shut down and delete the outdated subscriptions',
)]
final class SubscriptionTeardownCommand extends SubscriptionCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = $this->subscriptionEngineCriteria($input);
        $this->engine->teardown($criteria);

        return 0;
    }
}
