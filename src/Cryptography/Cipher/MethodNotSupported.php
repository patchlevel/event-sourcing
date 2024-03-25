<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography\Cipher;

use RuntimeException;

use function sprintf;

final class MethodNotSupported extends RuntimeException
{
    public function __construct(string $method)
    {
        parent::__construct(sprintf('Method %s not supported.', $method));
    }
}
