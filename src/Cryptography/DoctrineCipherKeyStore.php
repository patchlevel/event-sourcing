<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;

use function array_key_exists;
use function base64_decode;
use function base64_encode;

/**
 * @psalm-type Row = array{
 *     subject_id: non-empty-string,
 *     crypto_key: non-empty-string,
 *     crypto_method: non-empty-string,
 *     crypto_iv: non-empty-string
 * }
 */
final class DoctrineCipherKeyStore implements CipherKeyStore, DoctrineSchemaConfigurator
{
    /** @var array<string, CipherKey> */
    private array $keyCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly string $tableName = 'crypto_keys',
    ) {
    }

    public function get(string $id): CipherKey
    {
        if (array_key_exists($id, $this->keyCache)) {
            return $this->keyCache[$id];
        }

        /** @var Row|false $result */
        $result = $this->connection->fetchAssociative(
            "SELECT * FROM {$this->tableName} WHERE subject_id = :subject_id",
            ['subject_id' => $id],
        );

        if ($result === false) {
            throw new CipherKeyNotExists($id);
        }

        $this->keyCache[$id] = new CipherKey(
            base64_decode($result['crypto_key']),
            $result['crypto_method'],
            base64_decode($result['crypto_iv']),
        );

        return $this->keyCache[$id];
    }

    public function store(string $id, CipherKey $key): void
    {
        $this->connection->insert($this->tableName, [
            'subject_id' => $id,
            'crypto_key' => base64_encode($key->key),
            'crypto_method' => $key->method,
            'crypto_iv' => base64_encode($key->iv),
        ]);

        $this->keyCache[$id] = $key;
    }

    public function remove(string $id): void
    {
        $this->connection->delete($this->tableName, ['subject_id' => $id]);

        unset($this->keyCache[$id]);
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        if ($connection !== $this->connection) {
            return;
        }

        $table = $schema->createTable($this->tableName);
        $table->addColumn('subject_id', 'string')
            ->setNotnull(true)
            ->setLength(255);
        $table->addColumn('crypto_key', 'string')
            ->setNotnull(true)
            ->setLength(255);
        $table->addColumn('crypto_method', 'string')
            ->setNotnull(true)
            ->setLength(255);
        $table->addColumn('crypto_iv', 'string')
            ->setNotnull(true)
            ->setLength(255);
        $table->setPrimaryKey(['subject_id']);
    }

    public function clear(): void
    {
        $this->keyCache = [];
    }
}
