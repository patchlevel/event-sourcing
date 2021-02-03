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

    /** @var resource */
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
    }

    public function send(AggregateChanged $event): bool
    {
        $socketIsFresh = !$this->socket;
        $this->socket = $this->socket ?: $this->createSocket();

        if (!$this->socket) {
            return false;
        }

        $encodedPayload = base64_encode(serialize($event->serialize())) . "\n";

        set_error_handler([self::class, 'nullErrorHandler']);
        try {
            if (stream_socket_sendto($this->socket, $encodedPayload) !== -1) {
                return true;
            }

            if (!$socketIsFresh) {
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                fclose($this->socket);
                $this->socket = $this->createSocket();
            }

            if (stream_socket_sendto($this->socket, $encodedPayload) !== -1) {
                return true;
            }
        } finally {
            restore_error_handler();
        }

        return false;
    }

    /**
     * @return resource
     */
    private function createSocket()
    {
        set_error_handler([self::class, 'nullErrorHandler']);

        try {
            return stream_socket_client(
                $this->host,
                $errno,
                $errstr,
                3,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
            );
        } finally {
            restore_error_handler();
        }
    }

    private static function nullErrorHandler(int $errno, string $errstr): void
    {
        // no-op
    }
}
