<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography\Cipher;

final class CipherKey
{
    /**
     * @param non-empty-string $key
     * @param non-empty-string $method
     * @param non-empty-string $iv
     */
    public function __construct(
        public readonly string $key,
        public readonly string $method,
        public readonly string $iv,
    ) {
    }
}
