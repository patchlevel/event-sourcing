<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;

use function str_replace;
use function strpos;
use function substr;

/** @experimental */
final class StreamNameTranslator
{
    private function __construct()
    {
    }

    /** @pure */
    public static function streamName(string|AggregateRootMetadata $aggregate, string $aggregateId): string
    {
        if ($aggregate instanceof AggregateRootMetadata && $aggregate->streamName !== null) {
            return str_replace('{id}', $aggregateId, $aggregate->streamName);
        }

        return $aggregate . '-' . $aggregateId;
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
