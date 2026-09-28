<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Cryptography;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Cryptography\DoctrineCipherKeyStore;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipher;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
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

        $key = new CipherKey('the-key', 'aes256', 'the-iv');

        $store->store('foo', $key);
        $store->clear();

        $loaded = $store->get('foo');

        self::assertSame($key->key, $loaded->key);
        self::assertSame($key->method, $loaded->method);
        self::assertSame($key->iv, $loaded->iv);
    }

    public function testGetFromAnotherInstance(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $key = new CipherKey('the-key', 'aes256', 'the-iv');

        $store->store('foo', $key);

        $loaded = (new DoctrineCipherKeyStore($this->connection))->get('foo');

        self::assertEquals($key, $loaded);
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

        $store->get('foo');
    }

    public function testGetIsCached(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store('foo', new CipherKey('the-key', 'aes256', 'the-iv'));

        $this->connection->executeStatement('DELETE FROM crypto_keys');

        self::assertSame('the-key', $store->get('foo')->key);

        $store->clear();

        $this->expectException(CipherKeyNotExists::class);

        $store->get('foo');
    }

    public function testBinaryKeyRoundTrip(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $key = (new OpensslCipherKeyFactory())();

        $store->store('foo', $key);
        $store->clear();

        $loaded = $store->get('foo');

        self::assertSame($key->key, $loaded->key);
        self::assertSame($key->method, $loaded->method);
        self::assertSame($key->iv, $loaded->iv);
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
        $key = (new OpensslCipherKeyFactory())();

        $encrypted = $cipher->encrypt($key, 'john@example.com');

        $store->store('foo', $key);
        $store->clear();

        self::assertSame('john@example.com', $cipher->decrypt($store->get('foo'), $encrypted));
    }

    public function testStoreDuplicateSubject(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store('foo', new CipherKey('first-key', 'aes256', 'first-iv'));

        $exception = null;

        try {
            $store->store('foo', new CipherKey('second-key', 'aes256', 'second-iv'));
        } catch (UniqueConstraintViolationException $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);

        $store->clear();

        self::assertSame('first-key', $store->get('foo')->key);
    }

    public function testRemove(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store('foo', new CipherKey('the-key', 'aes256', 'the-iv'));
        $store->remove('foo');

        $this->expectException(CipherKeyNotExists::class);

        $store->get('foo');
    }

    public function testRemoveUnknownKey(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->remove('foo');

        self::assertEquals(0, $this->connection->fetchOne('SELECT COUNT(*) FROM crypto_keys'));
    }

    public function testRemoveOnlyAffectsTheSubject(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store('foo', new CipherKey('foo-key', 'aes256', 'foo-iv'));
        $store->store('bar', new CipherKey('bar-key', 'aes256', 'bar-iv'));

        $store->remove('foo');
        $store->clear();

        self::assertSame('bar-key', $store->get('bar')->key);

        $this->expectException(CipherKeyNotExists::class);

        $store->get('foo');
    }

    public function testRemoveAndStoreAgain(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store('foo', new CipherKey('first-key', 'aes256', 'first-iv'));
        $store->remove('foo');
        $store->store('foo', new CipherKey('second-key', 'aes256', 'second-iv'));
        $store->clear();

        self::assertSame('second-key', $store->get('foo')->key);
    }

    public function testMaxSubjectIdLength(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $id = str_repeat('a', 255);

        $store->store($id, new CipherKey('the-key', 'aes256', 'the-iv'));
        $store->clear();

        self::assertSame('the-key', $store->get($id)->key);
    }

    public function testCustomTableName(): void
    {
        $store = new DoctrineCipherKeyStore($this->connection, 'custom_crypto_keys');

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->store('foo', new CipherKey('the-key', 'aes256', 'the-iv'));
        $store->clear();

        self::assertSame('the-key', $store->get('foo')->key);
        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(*) FROM custom_crypto_keys'));
    }

    public function testConfigureSchemaSameDatabase(): void
    {
        $otherConnection = DbalManager::createConnection();

        $store = new DoctrineCipherKeyStore($this->connection);

        $schema = new Schema();

        $store->configureSchema($schema, $otherConnection);

        self::assertTrue($schema->hasTable('crypto_keys'));

        $otherConnection->close();
    }

    public function testConfigureSchemaNotSameDatabase(): void
    {
        $otherConnection = DbalManager::createConnection('other');

        $store = new DoctrineCipherKeyStore($this->connection);

        $schema = new Schema();

        $store->configureSchema($schema, $otherConnection);

        self::assertFalse($schema->hasTable('crypto_keys'));

        $otherConnection->close();
    }
}
