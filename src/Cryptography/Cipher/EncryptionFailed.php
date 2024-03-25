<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography\Cipher;

use RuntimeException;

final class EncryptionFailed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Encryption failed.');
    }
}
