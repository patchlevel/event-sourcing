<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function strpos;
use function substr;

final class StreamNameTranslator
{
    private function __construct()
    {
    }

    public static function streamName(string $aggregate, string $aggregateId): string
    {
        return $aggregate . '-' . $aggregateId;
    }

    public static function aggregateName(string $stream): string
    {
        $pos = strpos($stream, '-');

        if ($pos === false) {
            throw new InvalidAggregateStreamName($stream);
        }

        return substr($stream, 0, $pos);
    }

    public static function aggregateId(string $stream): string
    {
        $pos = strpos($stream, '-');

        if ($pos === false) {
            throw new InvalidAggregateStreamName($stream);
        }

        return substr($stream, $pos + 1);
    }
}
