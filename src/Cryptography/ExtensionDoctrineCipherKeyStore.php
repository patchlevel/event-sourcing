<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Schema\DoctrineHelper;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;

use function base64_decode;
use function base64_encode;

/**
 * @phpstan-type Row = array{
 *     id: non-empty-string,
 *     subject_id: non-empty-string,
 *     crypto_key: non-empty-string,
 *     crypto_method: non-empty-string,
 *     created_at: non-empty-string
 * }
 */
final class ExtensionDoctrineCipherKeyStore implements CipherKeyStore, DoctrineSchemaConfigurator
{
    private Type $dateTimeType;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $tableName = 'cryptography_keys',
    ) {
        $this->dateTimeType = Type::getType(Types::DATETIMETZ_IMMUTABLE);
    }

    public function get(string $id): CipherKey
    {
        /** @var Row|false $result */
        $result = $this->connection->fetchAssociative(
            "SELECT * FROM {$this->tableName} WHERE id = :id",
            ['id' => $id],
        );

        if ($result === false) {
            throw CipherKeyNotExists::forKeyId($id);
        }

        return new CipherKey(
            $result['id'],
            $result['subject_id'],
            base64_decode($result['crypto_key']),
            $result['crypto_method'],
            $this->dateTimeType->convertToPHPValue($result['created_at'], $this->connection->getDatabasePlatform()),
        );
    }

    public function currentKeyFor(string $subjectId): CipherKey
    {
        /** @var Row|false $result */
        $result = $this->connection->fetchAssociative(
            "SELECT * FROM {$this->tableName} WHERE subject_id = :subject_id",
            ['subject_id' => $subjectId],
        );

        if ($result === false) {
            throw CipherKeyNotExists::forSubjectId($subjectId);
        }

        return new CipherKey(
            $result['id'],
            base64_decode($result['crypto_key']),
            $result['crypto_method'],
            base64_decode($result['crypto_iv']),
            $this->dateTimeType->convertToPHPValue($result['created_at'], $this->connection->getDatabasePlatform()),
        );
    }

    public function store(CipherKey $key): void
    {
        $this->connection->insert($this->tableName, [
            'id' => $key->id,
            'subject_id' => $key->subjectId,
            'crypto_key' => base64_encode($key->key),
            'crypto_method' => $key->method,
            'created_at' => $this->dateTimeType->convertToDatabaseValue($key->createdAt, $this->connection->getDatabasePlatform()),
        ]);
    }

    public function remove(string $id): void
    {
        $this->connection->delete($this->tableName, ['id' => $id]);
    }

    public function removeWithSubjectId(string $subjectId): void
    {
        $this->connection->delete($this->tableName, ['subject_id' => $subjectId]);
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        if (!DoctrineHelper::sameDatabase($this->connection, $connection)) {
            return;
        }

        $table = $schema->createTable($this->tableName);
        $table->addColumn('id', 'string')
            ->setNotnull(true)
            ->setLength(255);
        $table->addColumn('subject_id', 'string')
            ->setNotnull(true)
            ->setLength(255);
        $table->addColumn('crypto_key', 'string')
            ->setNotnull(true)
            ->setLength(255);
        $table->addColumn('crypto_method', 'string')
            ->setNotnull(true)
            ->setLength(255);
        $table->addColumn('created_at', 'datetimetz_immutable')
            ->setNotnull(true);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['subject_id']);
    }
}
