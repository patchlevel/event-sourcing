<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use RuntimeException;

use function gettype;
use function sprintf;

final class OutboxHeaderIssue extends RuntimeException
{
    public static function missingHeader(string $header): self
    {
        return new self(sprintf('missing header "%s"', $header));
    }

    public static function invalidHeaderType(mixed $value): self
    {
        return new self(sprintf('Invalid header given: need type "int" got "%s"', gettype($value)));
    }
}
