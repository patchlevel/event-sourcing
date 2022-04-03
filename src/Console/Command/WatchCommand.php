<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Patchlevel\EventSourcing\WatchServer\WatchServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

final class WatchCommand extends Command
{
    private WatchServer $server;
    private Serializer $serializer;

    public function __construct(WatchServer $server, Serializer $serializer)
    {
        parent::__construct();

        $this->server = $server;
        $this->serializer = $serializer;
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:watch')
            ->setDescription('live stream of all aggregate events');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $this->server->start();

        $console->success(sprintf('Server listening on %s', $this->server->host()));
        $console->comment('Quit the server with CONTROL-C.');

        $this->server->listen(
            function (Message $message) use ($console): void {
                $console->message($this->serializer, $message);
            }
        );

        return 0;
    }
}
