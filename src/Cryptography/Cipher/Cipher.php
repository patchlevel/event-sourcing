<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography\Cipher;

interface Cipher
{
    /** @throws EncryptionFailed */
    public function encrypt(CipherKey $key, mixed $data): string;

    /** @throws DecryptionFailed */
    public function decrypt(CipherKey $key, string $data): mixed;
}
