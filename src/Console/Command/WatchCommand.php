<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\WatchServer\WatchServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    'event-sourcing:watch',
    'live stream of all aggregate events'
)]
final class WatchCommand extends Command
{
    private WatchServer $server;
    private EventSerializer $serializer;

    public function __construct(WatchServer $server, EventSerializer $serializer)
    {
        parent::__construct();

        $this->server = $server;
        $this->serializer = $serializer;
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
