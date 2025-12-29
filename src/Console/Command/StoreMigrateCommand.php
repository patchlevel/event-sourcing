<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Message\Pipe;
use Patchlevel\EventSourcing\Message\Translator\Translator;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;

#[AsCommand(
    'event-sourcing:store:migrate',
    'migrate events from one store to another',
)]
final class StoreMigrateCommand extends Command
{
    /** @param iterable<int, Translator> $translators */
    public function __construct(
        private readonly Store $store,
        private readonly Store $newStore,
        private readonly iterable $translators = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'buffer',
                null,
                InputOption::VALUE_REQUIRED,
                'How many messages should be buffered',
                1_000,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $buffer = InputHelper::positiveIntOrZero($input->getOption('buffer'));
        $style = new OutputStyle($input, $output);

        $style->info('Migration initialization...');

        $count = $this->store->count();
        $messages = $this->store->load();

        $style->progressStart($count);

        $bufferedMessages = [];

        $pipe = new Pipe(
            $messages,
            ...$this->translators,
        );

        foreach ($pipe as $message) {
            $bufferedMessages[] = $message;

            if (count($bufferedMessages) < $buffer) {
                continue;
            }

            $this->newStore->save(...$bufferedMessages);
            $bufferedMessages = [];
            $style->progressAdvance($buffer);
        }

        if (count($bufferedMessages) !== 0) {
            $this->newStore->save(...$bufferedMessages);
            $style->progressAdvance(count($bufferedMessages));
        }

        $style->progressFinish();
        $style->success('Migration finished');

        return 0;
    }
}
