<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\OutboxStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class OutboxInfoCommand extends Command
{
    private OutboxStore $store;
    private EventSerializer $serializer;

    public function __construct(OutboxStore $store, EventSerializer $serializer)
    {
        parent::__construct();

        $this->store = $store;
        $this->serializer = $serializer;
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:outbox:info')
            ->setDescription('displays the messages stored in the outbox store')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of messages to be displayed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $limit = InputHelper::nullableInt($input->getOption('limit'));

        $messages = $this->store->retrieveOutboxMessages($limit);

        foreach ($messages as $message) {
            $console->message($this->serializer, $message);
        }

        return 0;
    }
}
