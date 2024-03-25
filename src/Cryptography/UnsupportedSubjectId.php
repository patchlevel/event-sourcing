<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography;

use RuntimeException;

use function get_debug_type;
use function sprintf;

final class UnsupportedSubjectId extends RuntimeException
{
    public function __construct(mixed $subjectId)
    {
        parent::__construct(sprintf('Unsupported subject id: should be a string, got %s.', get_debug_type($subjectId)));
    }
}
