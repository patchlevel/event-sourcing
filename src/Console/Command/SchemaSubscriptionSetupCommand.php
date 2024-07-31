<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\SubscriptionStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:schema:subscription-setup',
    'setup subscription (pub/sub) for store',
)]
final class SchemaSubscriptionSetupCommand extends Command
{
    public function __construct(
        private readonly Store $store,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        if (!$this->store instanceof SubscriptionStore) {
            $io->error('store does not support subscriptions');

            return 1;
        }

        if (!$this->store->supportSubscription()) {
            $io->error('store does not support subscriptions');

            return 1;
        }

        $this->store->setupSubscription();

        return 0;
    }
}
