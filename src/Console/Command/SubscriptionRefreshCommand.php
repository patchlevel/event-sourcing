<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use LogicException;
use Patchlevel\EventSourcing\Subscription\Engine\CanRefreshSubscriptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    'event-sourcing:subscription:refresh',
    'Refresh subscriptions (run-mode, group)',
)]
final class SubscriptionRefreshCommand extends SubscriptionCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->engine instanceof CanRefreshSubscriptions) {
            throw new LogicException(sprintf(
                '"%s" does not implement "%s" and cannot call refresh.',
                $this->engine::class,
                CanRefreshSubscriptions::class,
            ));
        }

        $criteria = $this->subscriptionEngineCriteria($input);
        $this->engine->refresh($criteria);

        return 0;
    }
}
