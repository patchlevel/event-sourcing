<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Patchlevel\EventSourcing\Store\OutboxStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class OutboxInfoCommand extends Command
{
    private OutboxStore $store;
    private Serializer $serializer;

    public function __construct(OutboxStore $store, Serializer $serializer)
    {
        parent::__construct();

        $this->store = $store;
        $this->serializer = $serializer;
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:outbox:info')
            ->setDescription('show events from one aggregate')
            ->addArgument('limit', InputArgument::OPTIONAL, 'limit', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $limit = (int)$input->getArgument('limit');

        $messages = $this->store->retrieveOutboxMessages($limit);

        foreach ($messages as $message) {
            $console->message($this->serializer, $message);
        }

        return 0;
    }
}
