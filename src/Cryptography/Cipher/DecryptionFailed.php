<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography\Cipher;

use RuntimeException;

final class DecryptionFailed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Decryption failed.');
    }
}
