<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography;

use RuntimeException;

final class MissingSubjectId extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Missing subject id.');
    }
}
