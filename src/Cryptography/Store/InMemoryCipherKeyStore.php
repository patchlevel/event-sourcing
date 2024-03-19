<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography\Store;

use Patchlevel\EventSourcing\Cryptography\Cipher\CipherKey;

final class InMemoryCipherKeyStore implements CipherKeyStore
{
    /** @var array<string, CipherKey> */
    private array $keys = [];

    public function get(string $id): CipherKey
    {
        return $this->keys[$id] ?? throw new CipherKeyNotExists($id);
    }

    public function store(string $id, CipherKey $key): void
    {
        $this->keys[$id] = $key;
    }

    public function remove(string $id): void
    {
        unset($this->keys[$id]);
    }

    public function clear(): void
    {
        $this->keys = [];
    }
}
