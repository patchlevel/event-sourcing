<?php

namespace Patchlevel\EventSourcing\Tool\Watch;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

class WatchServerClient
{
    private string $host;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @param string $host The server host
     */
    public function __construct(string $host)
    {
        if (false === strpos($host, '://')) {
            $host = 'tcp://' . $host;
        }

        $this->host = $host;
    }

    public function send(AggregateChanged $event): bool
    {
        $socketIsFresh = !$this->socket;
        if (!$this->socket = $this->socket ?: $this->createSocket()) {
            return false;
        }

        $encodedPayload = base64_encode(serialize($event->serialize())) . "\n";

        set_error_handler([self::class, 'nullErrorHandler']);
        try {
            if (-1 !== stream_socket_sendto($this->socket, $encodedPayload)) {
                return true;
            }

            if (!$socketIsFresh) {
                stream_socket_shutdown($this->socket, \STREAM_SHUT_RDWR);
                fclose($this->socket);
                $this->socket = $this->createSocket();
            }

            if (-1 !== stream_socket_sendto($this->socket, $encodedPayload)) {
                return true;
            }
        } finally {
            restore_error_handler();
        }

        return false;
    }

    private static function nullErrorHandler($t, $m): void
    {
        // no-op
    }

    /**
     * @return resource
     */
    private function createSocket()
    {
        set_error_handler([self::class, 'nullErrorHandler']);
        try {
            return stream_socket_client($this->host, $errno, $errstr, 3,
                \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT);
        } finally {
            restore_error_handler();
        }
    }
}
