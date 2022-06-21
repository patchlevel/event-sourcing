<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Outbox\OutboxConsumer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class OutboxConsumeCommand extends Command
{
    protected static $defaultName = 'event-sourcing:outbox:consume';
    protected static $defaultDescription = 'published the messages from the outbox store';

    public function __construct(private OutboxConsumer $consumer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many messages should be consumed in one run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = InputHelper::nullableInt($input->getOption('limit'));

        $this->consumer->consume($limit);

        return 0;
    }
}
