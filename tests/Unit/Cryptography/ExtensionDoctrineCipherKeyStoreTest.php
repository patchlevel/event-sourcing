<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Cryptography\ExtensionDoctrineCipherKeyStore;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function base64_encode;

#[CoversClass(ExtensionDoctrineCipherKeyStore::class)]
final class ExtensionDoctrineCipherKeyStoreTest extends TestCase
{
    public function testGet(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with('SELECT * FROM cryptography_keys WHERE id = :id', ['id' => 'foo'])
            ->willReturn([
                'id' => 'foo',
                'subject_id' => 'profile-1',
                'crypto_key' => base64_encode('secret-key'),
                'crypto_method' => 'aes-256-gcm',
                'created_at' => '2024-01-01 10:00:00',
            ]);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());

        $store = new ExtensionDoctrineCipherKeyStore($connection);

        self::assertEquals(
            new CipherKey(
                'foo',
                'profile-1',
                'secret-key',
                'aes-256-gcm',
                new DateTimeImmutable('2024-01-01 10:00:00'),
            ),
            $store->get('foo'),
        );
    }

    public function testGetNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with('SELECT * FROM cryptography_keys WHERE id = :id', ['id' => 'foo'])
            ->willReturn(false);

        $store = new ExtensionDoctrineCipherKeyStore($connection);

        $this->expectException(CipherKeyNotExists::class);

        $store->get('foo');
    }

    public function testCurrentKeyFor(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with('SELECT * FROM cryptography_keys WHERE subject_id = :subject_id', ['subject_id' => 'profile-1'])
            ->willReturn([
                'id' => 'foo',
                'subject_id' => 'profile-1',
                'crypto_key' => base64_encode('secret-key'),
                'crypto_method' => 'aes-256-gcm',
                'created_at' => '2024-01-01 10:00:00',
            ]);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());

        $store = new ExtensionDoctrineCipherKeyStore($connection);

        self::assertEquals(
            new CipherKey(
                'foo',
                'profile-1',
                'secret-key',
                'aes-256-gcm',
                new DateTimeImmutable('2024-01-01 10:00:00'),
            ),
            $store->currentKeyFor('profile-1'),
        );
    }

    public function testCurrentKeyForNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with('SELECT * FROM cryptography_keys WHERE subject_id = :subject_id', ['subject_id' => 'profile-1'])
            ->willReturn(false);

        $store = new ExtensionDoctrineCipherKeyStore($connection);

        $this->expectException(CipherKeyNotExists::class);

        $store->currentKeyFor('profile-1');
    }

    public function testStore(): void
    {
        $platform = new SQLitePlatform();
        $createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $expectedDate = Type::getType(Types::DATETIMETZ_IMMUTABLE)->convertToDatabaseValue($createdAt, $platform);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('cryptography_keys', [
                'id' => 'foo',
                'subject_id' => 'profile-1',
                'crypto_key' => base64_encode('secret-key'),
                'crypto_method' => 'aes-256-gcm',
                'created_at' => $expectedDate,
            ]);

        $store = new ExtensionDoctrineCipherKeyStore($connection);

        $store->store(new CipherKey(
            'foo',
            'profile-1',
            'secret-key',
            'aes-256-gcm',
            $createdAt,
        ));
    }

    public function testRemove(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('cryptography_keys', ['id' => 'foo']);

        $store = new ExtensionDoctrineCipherKeyStore($connection);

        $store->remove('foo');
    }

    public function testRemoveWithSubjectId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('cryptography_keys', ['subject_id' => 'profile-1']);

        $store = new ExtensionDoctrineCipherKeyStore($connection);

        $store->removeWithSubjectId('profile-1');
    }

    public function testCustomTableName(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('my_keys', ['id' => 'foo']);

        $store = new ExtensionDoctrineCipherKeyStore($connection, 'my_keys');

        $store->remove('foo');
    }

    public function testConfigureSchema(): void
    {
        $connection = $this->createMock(Connection::class);

        $store = new ExtensionDoctrineCipherKeyStore($connection);

        $expectedSchema = new Schema();
        $table = $expectedSchema->createTable('cryptography_keys');
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

        $schema = new Schema();
        $store->configureSchema($schema, $connection);

        self::assertEquals($expectedSchema, $schema);
    }

    public function testConfigureSchemaWithDifferentDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db']);

        $differentConnection = $this->createMock(Connection::class);
        $differentConnection
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db2']);

        $store = new ExtensionDoctrineCipherKeyStore($connection);

        $schema = new Schema();
        $store->configureSchema($schema, $differentConnection);

        self::assertEquals(new Schema(), $schema);
    }
}
