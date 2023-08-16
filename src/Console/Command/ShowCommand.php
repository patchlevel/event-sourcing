<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    'event-sourcing:show',
    'show events from the event store',
)]
final class ShowCommand extends Command
{
    public function __construct(
        private readonly Store $store,
        private readonly EventSerializer $serializer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'How many messages should be displayed',
                10,
            )
            ->addOption(
                'forward',
                null,
                InputOption::VALUE_NONE,
                'Show messages from the beginning of the stream',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = InputHelper::positiveIntOrZero($input->getOption('limit'));
        $forward = InputHelper::bool($input->getOption('forward'));

        $console = new OutputStyle($input, $output);

        $maxCount = $this->store->count();
        $stream = $this->store->load(null, !$forward);

        $currentCount = 0;

        do {
            $i = 0;

            foreach ($stream as $message) {
                $i++;
                $currentCount++;

                $console->message($this->serializer, $message);

                if ($i >= $limit) {
                    break;
                }
            }

            if ($currentCount >= $maxCount) {
                $console->info(
                    sprintf('No more messages (%d/%d)', $currentCount, $maxCount),
                );

                break;
            }

            $continue = $console->confirm(
                sprintf(
                    'Show next %d messages? (%d/%d)',
                    $limit,
                    $currentCount,
                    $maxCount,
                ),
            );
        } while ($continue);

        return 0;
    }
}
