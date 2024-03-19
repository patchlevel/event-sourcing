<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography\Cipher;

interface CipherKeyFactory
{
    /** @throws CreateCipherKeyFailed */
    public function __invoke(): CipherKey;
}
