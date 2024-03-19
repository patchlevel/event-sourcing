<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography;

use Patchlevel\EventSourcing\Cryptography\Cipher\Cipher;
use Patchlevel\EventSourcing\Cryptography\Cipher\CipherKey;
use Patchlevel\EventSourcing\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\EventSourcing\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\EventSourcing\Cryptography\DefaultEventPayloadCryptographer;
use Patchlevel\EventSourcing\Cryptography\MissingSubjectId;
use Patchlevel\EventSourcing\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\EventSourcing\Cryptography\Store\CipherKeyStore;
use Patchlevel\EventSourcing\Cryptography\UnsupportedSubjectId;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\EmailChanged;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Cryptography\DefaultEventPayloadCryptographer */
final class DefaultEventPayloadCryptographerTest extends TestCase
{
    use ProphecyTrait;

    public function testSkipEncrypt(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get(Argument::any())->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new DefaultEventPayloadCryptographer(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $payload = ['id' => 'foo', 'email' => 'info@patchlevel.de'];

        $result = $cryptographer->encrypt(ProfileVisited::class, ['id' => 'foo', 'email' => 'info@patchlevel.de']);

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

        $cryptographer = new DefaultEventPayloadCryptographer(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->encrypt(EmailChanged::class, ['id' => 'foo', 'email' => 'info@patchlevel.de']);

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

        $cryptographer = new DefaultEventPayloadCryptographer(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->encrypt(EmailChanged::class, ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertEquals(['id' => 'foo', 'email' => 'encrypted'], $result);
    }

    public function testSkipDecrypt(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get(Argument::any())->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new DefaultEventPayloadCryptographer(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $payload = ['id' => 'foo', 'email' => 'info@patchlevel.de'];

        $result = $cryptographer->decrypt(ProfileVisited::class, ['id' => 'foo', 'email' => 'info@patchlevel.de']);

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

        $cryptographer = new DefaultEventPayloadCryptographer(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->decrypt(EmailChanged::class, ['id' => 'foo', 'email' => 'encrypted']);

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

        $cryptographer = new DefaultEventPayloadCryptographer(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->decrypt(EmailChanged::class, ['id' => 'foo', 'email' => 'encrypted']);

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

        $cryptographer = new DefaultEventPayloadCryptographer(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->decrypt(EmailChanged::class, ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => 'info@patchlevel.de'], $result);
    }

    public function testUnsupportedSubjectId(): void
    {
        $this->expectException(UnsupportedSubjectId::class);

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new DefaultEventPayloadCryptographer(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $cryptographer->decrypt(EmailChanged::class, ['id' => null, 'email' => 'encrypted']);
    }

    public function testMissingSubjectId(): void
    {
        $this->expectException(MissingSubjectId::class);

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new DefaultEventPayloadCryptographer(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $cryptographer->decrypt(EmailChanged::class, ['email' => 'encrypted']);
    }

    public function testCreateWithOpenssl(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);

        $cryptographer = DefaultEventPayloadCryptographer::createWithOpenssl(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore->reveal(),
        );

        self::assertInstanceOf(DefaultEventPayloadCryptographer::class, $cryptographer);
    }
}
