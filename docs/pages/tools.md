# Tools

The library offers a few developer tools to simplify the work and debug of event sourcing.

## Watch

We have implemented a watch server that can be used for development.
Every event that is saved is sent to the watch server using a watch listener.
You can subscribe to it and display the information anywhere, e.g. in the CLI.

### Watch client

The watch client and the listener are used to send all events that are saved to a specific host.

```php
use Patchlevel\EventSourcing\WatchServer\SocketWatchServerClient;
use Patchlevel\EventSourcing\WatchServer\WatchListener;

$watchServerClient = new SocketWatchServerClient('127.0.0.1:5000', $serializer);
$watchListener = new WatchListener($watchServerClient);
```

!!! warning

    This should only be used for dev purposes and should not be registered in production.

### Watch server

The watch server is used to receive all events that are sent.
You can subscribe to the watch server and process or display each event as you wish.
As soon as you execute `start`, the server will be started until you terminate the php process.

```php
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\WatchServer\SocketWatchServer;

$watchServer = new SocketWatchServer('127.0.0.1:5000', $serializer);
$watchServer->listen(
    function (Message $message) {
        var_dump($message);
    }
);
$watchServer->start();
```

!!! warning

    The host must match the one defined in the watch server client.

Here is an example of how to use it with a symfony cli.

```php
use Patchlevel\EventSourcing\Console\Command;
use Patchlevel\EventSourcing\WatchServer\SocketWatchServer;
use Symfony\Component\Console\Application;

$cli = new Application('Event-Sourcing CLI');
$cli->setCatchExceptions(true);

$watchServer = new SocketWatchServer('127.0.0.1:5000', $serializer);
$command = new WatchCommand($watchServer);

$cli->addCommands([
    /* more commands */
    new Command\WatchCommand($watchServer),
]);

$cli->run();
```

!!! note

    The command can be terminated with `ctrl+c` or `control+c`.