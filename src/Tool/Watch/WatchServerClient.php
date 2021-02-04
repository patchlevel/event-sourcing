<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tool\Watch;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function base64_encode;
use function fclose;
use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function stream_socket_client;
use function stream_socket_sendto;
use function stream_socket_shutdown;
use function strpos;

use const STREAM_CLIENT_ASYNC_CONNECT;
use const STREAM_CLIENT_CONNECT;
use const STREAM_SHUT_RDWR;

class WatchServerClient
{
    private string $host;

    /** @var resource|null */
    private $socket;

    /**
     * @param string $host The server host
     */
    public function __construct(string $host)
    {
        if (strpos($host, '://') === false) {
            $host = 'tcp://' . $host;
        }

        $this->host = $host;
        $this->socket = null;
    }

    public function send(AggregateChanged $event): bool
    {
        $socket = $this->createSocket();

        if (!$socket) {
            return false;
        }

        $encodedPayload = base64_encode(serialize($event->serialize())) . "\n";

        set_error_handler([self::class, 'nullErrorHandler']);

        try {
            if (stream_socket_sendto($socket, $encodedPayload) !== -1) {
                return true;
            }

            $this->closeSocket();
            $socket = $this->createSocket();

            if (!$socket) {
                return false;
            }

            if (stream_socket_sendto($socket, $encodedPayload) !== -1) {
                return true;
            }
        } finally {
            restore_error_handler();
        }

        return false;
    }

    /**
     * @return resource|null
     */
    private function createSocket()
    {
        if ($this->socket) {
            return $this->socket;
        }

        set_error_handler([self::class, 'nullErrorHandler']);

        try {
            $socket = stream_socket_client(
                $this->host,
                $errno,
                $errstr,
                3,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
            );

            if (!$socket) {
                return null;
            }

            $this->socket = $socket;

            return $socket;
        } finally {
            restore_error_handler();
        }
    }

    private function closeSocket(): void
    {
        $socket = $this->socket;

        if (!$socket) {
            return;
        }

        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
        fclose($socket);

        $this->socket = null;
    }

    /** @internal */
    public function nullErrorHandler(int $errno, string $errstr): bool
    {
        return false;
    }
}
