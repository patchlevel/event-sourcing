<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

/**
 * Collects all headers whose names could not be resolved to a registered class,
 * e.g. because the header class was removed. It keeps the raw names and payloads
 * so the data is preserved and can be written back without loss.
 */
final class MissingHeaders
{
    /** @param array<string, array<mixed>> $headers */
    public function __construct(
        public readonly array $headers,
    ) {
    }
}
