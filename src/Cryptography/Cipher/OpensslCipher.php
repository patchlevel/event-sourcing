<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography\Cipher;

use JsonException;

use function base64_decode;
use function base64_encode;
use function json_decode;
use function json_encode;
use function openssl_decrypt;
use function openssl_encrypt;

use const JSON_THROW_ON_ERROR;

final class OpensslCipher implements Cipher
{
    public function encrypt(CipherKey $key, mixed $data): string
    {
        $encryptedData = @openssl_encrypt(
            $this->dataEncode($data),
            $key->method,
            $key->key,
            0,
            $key->iv,
        );

        if ($encryptedData === false) {
            throw new EncryptionFailed();
        }

        return base64_encode($encryptedData);
    }

    public function decrypt(CipherKey $key, string $data): mixed
    {
        $data = @openssl_decrypt(
            base64_decode($data),
            $key->method,
            $key->key,
            0,
            $key->iv,
        );

        if ($data === false) {
            throw new DecryptionFailed();
        }

        try {
            return $this->dataDecode($data);
        } catch (JsonException) {
            throw new DecryptionFailed();
        }
    }

    private function dataEncode(mixed $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function dataDecode(string $data): mixed
    {
        return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    }
}
