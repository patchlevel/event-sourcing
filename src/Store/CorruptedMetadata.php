<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function sprintf;

final class CorruptedMetadata extends StoreException
{
    public static function fromEntryMismatch(string $expectedId, string $actualId): self
    {
        return new self(sprintf(
            'Corrupted metadata: expected id is %s get %s',
            $expectedId,
            $actualId
        ));
    }

    public static function fromMissingEntry(string $expectedId): self
    {
        return new self(sprintf(
            'Corrupted metadata: expected id is %s there but it is missing',
            $expectedId
        ));
    }
}
