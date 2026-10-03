<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
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
        $buffer = InputHelper::positiveInt($input->getOption('buffer'));
        $style = new OutputStyle($input, $output);

        $style->info('Migration initialization...');

        $count = $this->store->count();
        $stream = $this->store->load();

        $style->progressStart($count);

        $translatedStream = $stream->transform(...$this->translators);

        foreach ($translatedStream->chunk($buffer) as $chunk) {
            $messages = $chunk->toList();

            $this->newStore->save(...$messages);
            $style->progressAdvance(count($messages));
        }

        $style->progressFinish();
        $style->success('Migration finished');

        return 0;
    }
}
