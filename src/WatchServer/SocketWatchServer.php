<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Closure;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

use function fclose;
use function feof;
use function fgets;
use function sprintf;
use function stream_select;
use function stream_socket_accept;
use function stream_socket_server;
use function strpos;

final class SocketWatchServer implements WatchServer
{
    private string $host;

    /** @var resource|null */
    private $socket;

    private LoggerInterface $logger;

    public function __construct(string $host, private MessageSerializer $serializer, LoggerInterface|null $logger = null)
    {
        if (strpos($host, '://') === false) {
            $host = 'tcp://' . $host;
        }

        $this->host = $host;
        $this->logger = $logger ?? new NullLogger();
        $this->socket = null;
    }

    public function start(): void
    {
        if ($this->socket) {
            return;
        }

        $socket = stream_socket_server($this->host, $errno, $errstr);

        if ($socket === false) {
            throw new RuntimeException(sprintf('Server start failed on "%s": ', $this->host) . $errstr . ' ' . $errno);
        }

        $this->socket = $socket;
    }

    public function listen(Closure $callback): void
    {
        $socket = $this->socket();

        foreach ($this->messages($socket) as $clientId => $clientMessage) {
            $this->logger->info('Received a payload from client {clientId}', ['clientId' => $clientId]);

            $message = $this->serializer->deserialize($clientMessage);

            $callback($message, $clientId);
        }
    }

    public function host(): string
    {
        return $this->host;
    }

    /** @return resource */
    private function socket()
    {
        $this->start();

        $socket = $this->socket;

        if (!$socket) {
            throw new RuntimeException();
        }

        return $socket;
    }

    /**
     * @param resource $socket
     *
     * @return iterable<int, string>
     */
    private function messages($socket): iterable
    {
        $sockets = [(int)$socket => $socket];
        $write = [];

        while (true) {
            $read = $sockets;
            stream_select($read, $write, $write, null);

            foreach ($read as $stream) {
                if ($socket === $stream) {
                    $stream = stream_socket_accept($socket);

                    if ($stream === false) {
                        continue;
                    }

                    $sockets[(int)$stream] = $stream;
                } elseif (feof($stream)) {
                    unset($sockets[(int)$stream]);
                    fclose($stream);
                } else {
                    $content = fgets($stream);

                    if ($content === false) {
                        continue;
                    }

                    yield (int)$stream => $content;
                }
            }
        }
    }
}
