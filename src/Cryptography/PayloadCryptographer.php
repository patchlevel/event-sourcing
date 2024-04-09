<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography;

interface PayloadCryptographer
{
    /**
     * @param class-string         $class
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function encrypt(string $class, array $data): array;

    /**
     * @param class-string         $class
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function decrypt(string $class, array $data): array;
}
