<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Cryptography;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Cryptography\DoctrineCipherKeyStore;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\OpensslCipher;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\OpensslCipherKeyFactory;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[CoversNothing]
final class DoctrineCipherKeyStoreTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testStoreAndGet(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $key = new CipherKey('key-1', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00'));

        $store->store($key);

        $loaded = $store->get('key-1');

        self::assertSame('key-1', $loaded->id);
        self::assertSame('foo', $loaded->subjectId);
        self::assertSame($key->key, $loaded->key);
        self::assertSame($key->method, $loaded->method);
        self::assertSame('2020-01-01 00:00:00', $loaded->createdAt->format('Y-m-d H:i:s'));
    }

    public function testGetUnknownKey(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $this->expectException(CipherKeyNotExists::class);
        $this->expectExceptionMessage('Cipher key with id "key-1" does not exist.');

        $store->get('key-1');
    }

    public function testCurrentKeyFor(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store(new CipherKey('key-1', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));

        self::assertSame('key-1', $store->currentKeyFor('foo')->id);
    }

    public function testCurrentKeyForUnknownSubject(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store(new CipherKey('key-1', 'bar', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));

        $this->expectException(CipherKeyNotExists::class);
        $this->expectExceptionMessage('Cipher key for subject id "foo" does not exist.');

        $store->currentKeyFor('foo');
    }

    public function testCurrentKeyForReturnsTheNewestKey(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store(new CipherKey('key-b', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-02 00:00:00')));
        $store->store(new CipherKey('key-c', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-03 00:00:00')));
        $store->store(new CipherKey('key-a', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));

        self::assertSame('key-c', $store->currentKeyFor('foo')->id);
    }

    public function testCurrentKeyForWithSameCreatedAtIsDeterministic(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store(new CipherKey('key-b', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));
        $store->store(new CipherKey('key-c', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));
        $store->store(new CipherKey('key-a', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));

        // the created_at has only second precision, so the id decides between keys of the same second
        self::assertSame('key-c', $store->currentKeyFor('foo')->id);
    }

    public function testOldKeysStayLoadable(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store(new CipherKey('key-1', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));
        $store->store(new CipherKey('key-2', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-02 00:00:00')));

        self::assertSame('foo', $store->get('key-1')->subjectId);
        self::assertSame('foo', $store->get('key-2')->subjectId);
    }

    public function testGetFromAnotherInstance(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $key = new CipherKey('key-1', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00'));

        $store->store($key);

        $loaded = (new DoctrineCipherKeyStore($this->connection))->get('key-1');

        self::assertSame($key->key, $loaded->key);
    }

    public function testStoredKeyDecryptsData(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $cipher = new OpensslCipher();
        $key = (new OpensslCipherKeyFactory())('foo');

        $encrypted = $cipher->encrypt($key, 'john@example.com');

        $store->store($key);

        self::assertSame('john@example.com', $cipher->decrypt($store->currentKeyFor('foo'), $encrypted));
    }

    public function testStoreDuplicateId(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store(new CipherKey('key-1', 'foo', 'first-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));

        $exception = null;

        try {
            $store->store(new CipherKey('key-1', 'bar', 'second-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-02 00:00:00')));
        } catch (UniqueConstraintViolationException $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertSame('first-key', $store->get('key-1')->key);
    }

    public function testRemove(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store(new CipherKey('key-1', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));
        $store->store(new CipherKey('key-2', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-02 00:00:00')));

        $store->remove('key-2');

        self::assertSame('key-1', $store->currentKeyFor('foo')->id);

        $this->expectException(CipherKeyNotExists::class);

        $store->get('key-2');
    }

    public function testRemoveUnknownKey(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store(new CipherKey('key-1', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));

        $store->remove('key-2');

        self::assertSame('key-1', $store->get('key-1')->id);
    }

    public function testRemoveWithSubjectId(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store(new CipherKey('key-1', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));
        $store->store(new CipherKey('key-2', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-02 00:00:00')));
        $store->store(new CipherKey('key-3', 'bar', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));

        $store->removeWithSubjectId('foo');

        self::assertSame('key-3', $store->currentKeyFor('bar')->id);

        $this->expectException(CipherKeyNotExists::class);

        $store->currentKeyFor('foo');
    }

    public function testMaxIdLength(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $id = str_repeat('a', 255);
        $subjectId = str_repeat('b', 255);

        $store->store(new CipherKey($id, $subjectId, 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));

        self::assertSame($id, $store->currentKeyFor($subjectId)->id);
    }

    public function testCustomTableName(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection, 'custom_cryptography_keys');

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store(new CipherKey('key-1', 'foo', 'the-key', 'aes-256-gcm', new DateTimeImmutable('2020-01-01 00:00:00')));

        self::assertSame('key-1', $store->currentKeyFor('foo')->id);
        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(*) FROM custom_cryptography_keys'));
    }

    public function testConfigureSchemaSameDatabase(): void
    {
        $otherConnection = DbalManager::createConnection();

        $store = new DoctrineCipherKeyStore($this->connection);

        $schema = new Schema();

        $store->configureSchema($schema, $otherConnection);

        self::assertTrue($schema->hasTable('cryptography_keys'));

        $otherConnection->close();
    }

    public function testConfigureSchemaNotSameDatabase(): void
    {
        $otherConnection = DbalManager::createConnection('other');

        $store = new DoctrineCipherKeyStore($this->connection);

        $schema = new Schema();

        $store->configureSchema($schema, $otherConnection);

        self::assertFalse($schema->hasTable('cryptography_keys'));

        $otherConnection->close();
    }
}
