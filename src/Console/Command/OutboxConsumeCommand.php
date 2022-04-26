<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Outbox\OutboxConsumer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class OutboxConsumeCommand extends Command
{
    public function __construct(private OutboxConsumer $consumer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:outbox:consume')
            ->setDescription('show events from one aggregate')
            ->addArgument('limit', InputArgument::OPTIONAL, 'limit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int)$input->getArgument('limit');

        $this->consumer->consume($limit);

        return 0;
    }
}
