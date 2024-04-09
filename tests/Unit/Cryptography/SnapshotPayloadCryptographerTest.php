<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography;

use Patchlevel\EventSourcing\Cryptography\Cipher\Cipher;
use Patchlevel\EventSourcing\Cryptography\Cipher\CipherKey;
use Patchlevel\EventSourcing\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\EventSourcing\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\EventSourcing\Cryptography\MissingSubjectId;
use Patchlevel\EventSourcing\Cryptography\SnapshotPayloadCryptographer;
use Patchlevel\EventSourcing\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\EventSourcing\Cryptography\Store\CipherKeyStore;
use Patchlevel\EventSourcing\Cryptography\UnsupportedSubjectId;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Cryptography\SnapshotPayloadCryptographer */
final class SnapshotPayloadCryptographerTest extends TestCase
{
    use ProphecyTrait;

    public function testSkipEncrypt(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get(Argument::any())->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new SnapshotPayloadCryptographer(
            new AttributeAggregateRootMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $payload = ['id' => 'foo', 'email' => 'info@patchlevel.de'];

        $result = $cryptographer->encrypt(Profile::class, ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertSame($payload, $result);
    }

    public function testEncryptWithMissingKey(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get('foo')->willThrow(new CipherKeyNotExists('foo'));
        $cipherKeyStore->store('foo', $cipherKey)->shouldBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipherKeyFactory->__invoke()->willReturn($cipherKey)->shouldBeCalledOnce();

        $cipher = $this->prophesize(Cipher::class);
        $cipher
            ->encrypt($cipherKey, 'info@patchlevel.de')
            ->willReturn('encrypted')
            ->shouldBeCalledOnce();

        $cryptographer = new SnapshotPayloadCryptographer(
            new AttributeAggregateRootMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->encrypt(ProfileWithSnapshot::class, ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertEquals(['id' => 'foo', 'email' => 'encrypted'], $result);
    }

    public function testEncryptWithExistingKey(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get('foo')->willReturn($cipherKey);
        $cipherKeyStore->store('foo', Argument::type(CipherKey::class))->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipherKeyFactory->__invoke()->shouldNotBeCalled();

        $cipher = $this->prophesize(Cipher::class);
        $cipher
            ->encrypt($cipherKey, 'info@patchlevel.de')
            ->willReturn('encrypted')
            ->shouldBeCalledOnce();

        $cryptographer = new SnapshotPayloadCryptographer(
            new AttributeAggregateRootMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->encrypt(ProfileWithSnapshot::class, ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertEquals(['id' => 'foo', 'email' => 'encrypted'], $result);
    }

    public function testSkipDecrypt(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get(Argument::any())->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new SnapshotPayloadCryptographer(
            new AttributeAggregateRootMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $payload = ['id' => 'foo', 'email' => 'info@patchlevel.de'];

        $result = $cryptographer->decrypt(Profile::class, ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertSame($payload, $result);
    }

    public function testDecryptWithMissingKey(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get('foo')->willThrow(new CipherKeyNotExists('foo'));

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipherKeyFactory->__invoke()->shouldNotBeCalled();

        $cipher = $this->prophesize(Cipher::class);
        $cipher->decrypt()->shouldNotBeCalled();

        $cryptographer = new SnapshotPayloadCryptographer(
            new AttributeAggregateRootMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->decrypt(ProfileWithSnapshot::class, ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => 'fallback'], $result);
    }

    public function testDecryptWithInvalidKey(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get('foo')->willReturn($cipherKey);
        $cipherKeyStore->store('foo', Argument::type(CipherKey::class))->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipherKeyFactory->__invoke()->shouldNotBeCalled();

        $cipher = $this->prophesize(Cipher::class);
        $cipher
            ->decrypt($cipherKey, 'encrypted')
            ->willThrow(new DecryptionFailed())
            ->shouldBeCalledOnce();

        $cryptographer = new SnapshotPayloadCryptographer(
            new AttributeAggregateRootMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->decrypt(ProfileWithSnapshot::class, ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => 'fallback'], $result);
    }

    public function testDecryptWithExistingKey(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get('foo')->willReturn($cipherKey);
        $cipherKeyStore->store('foo', Argument::type(CipherKey::class))->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipherKeyFactory->__invoke()->shouldNotBeCalled();

        $cipher = $this->prophesize(Cipher::class);
        $cipher
            ->decrypt($cipherKey, 'encrypted')
            ->willReturn('info@patchlevel.de')
            ->shouldBeCalledOnce();

        $cryptographer = new SnapshotPayloadCryptographer(
            new AttributeAggregateRootMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->decrypt(ProfileWithSnapshot::class, ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => 'info@patchlevel.de'], $result);
    }

    public function testUnsupportedSubjectId(): void
    {
        $this->expectException(UnsupportedSubjectId::class);

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new SnapshotPayloadCryptographer(
            new AttributeAggregateRootMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $cryptographer->decrypt(ProfileWithSnapshot::class, ['id' => null, 'email' => 'encrypted']);
    }

    public function testMissingSubjectId(): void
    {
        $this->expectException(MissingSubjectId::class);

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new SnapshotPayloadCryptographer(
            new AttributeAggregateRootMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $cryptographer->decrypt(ProfileWithSnapshot::class, ['email' => 'encrypted']);
    }

    public function testCreateWithOpenssl(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);

        $cryptographer = SnapshotPayloadCryptographer::createWithOpenssl(
            new AttributeAggregateRootMetadataFactory(),
            $cipherKeyStore->reveal(),
        );

        self::assertInstanceOf(SnapshotPayloadCryptographer::class, $cryptographer);
    }
}
