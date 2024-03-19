<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography\Cipher;

use function function_exists;
use function in_array;
use function openssl_cipher_iv_length;
use function openssl_cipher_key_length;
use function openssl_get_cipher_methods;
use function openssl_random_pseudo_bytes;

final class OpensslCipherKeyFactory implements CipherKeyFactory
{
    public const DEFAULT_METHOD = 'aes128';

    private readonly int $keyLength;

    private readonly int $ivLength;

    /** @param non-empty-string $method */
    public function __construct(
        private readonly string $method = self::DEFAULT_METHOD,
    ) {
        if (!self::methodSupported($this->method)) {
            throw new MethodNotSupported($this->method);
        }

        $keyLength = 16;

        if (function_exists('openssl_cipher_key_length')) {
            $keyLength = @openssl_cipher_key_length($this->method);
        }

        $ivLength = @openssl_cipher_iv_length($this->method);

        if ($keyLength === false || $ivLength === false) {
            throw new MethodNotSupported($this->method);
        }

        $this->keyLength = $keyLength;
        $this->ivLength = $ivLength;
    }

    public function __invoke(): CipherKey
    {
        return new CipherKey(
            openssl_random_pseudo_bytes($this->keyLength),
            $this->method,
            openssl_random_pseudo_bytes($this->ivLength),
        );
    }

    /** @return list<string> */
    public static function supportedMethods(): array
    {
        return openssl_get_cipher_methods(true);
    }

    public static function methodSupported(string $method): bool
    {
        return in_array($method, self::supportedMethods(), true);
    }
}
