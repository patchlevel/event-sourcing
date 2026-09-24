<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

use Closure;
use Patchlevel\EventSourcing\Message\Stream;

/** @experimental */
final class LoadedStream
{
    /**
     * @param int<0, max>        $playhead the playhead of the aggregate before the first message
     * @param Closure(): Version $version
     */
    public function __construct(
        public readonly Stream $stream,
        public readonly int $playhead,
        private readonly Closure $version,
    ) {
    }

    /**
     * The version after the last message. It is only reliable once the stream was fully consumed.
     */
    public function version(): Version
    {
        return ($this->version)();
    }
}
