<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Console\EventPrinter;
use Patchlevel\EventSourcing\WatchServer\WatchServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

class WatchCommand extends Command
{
    private WatchServer $server;

    public function __construct(WatchServer $server)
    {
        parent::__construct();

        $this->server = $server;
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:watch')
            ->setDescription('live stream of all aggregate events');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);

        $this->server->start();

        $console->success(sprintf('Server listening on %s', $this->server->host()));
        $console->comment('Quit the server with CONTROL-C.');

        $dumper = new EventPrinter();

        $this->server->listen(static function (AggregateChanged $event) use ($dumper, $console): void {
            $dumper->write($console, $event);
        });

        return 0;
    }
}
