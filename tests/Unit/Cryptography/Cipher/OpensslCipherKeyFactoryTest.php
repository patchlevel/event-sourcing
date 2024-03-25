<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography\Cipher;

use Patchlevel\EventSourcing\Cryptography\Cipher\MethodNotSupported;
use Patchlevel\EventSourcing\Cryptography\Cipher\OpensslCipherKeyFactory;
use PHPUnit\Framework\TestCase;

use function strlen;

/** @covers \Patchlevel\EventSourcing\Cryptography\Cipher\OpensslCipherKeyFactory */
final class OpensslCipherKeyFactoryTest extends TestCase
{
    public function testCreateKey(): void
    {
        $cipherKeyFactory = new OpensslCipherKeyFactory();
        $cipherKey = $cipherKeyFactory();

        $this->assertSame(16, strlen($cipherKey->key));
        $this->assertSame('aes128', $cipherKey->method);
        $this->assertSame(16, strlen($cipherKey->iv));
    }

    public function testMethodNotSupported(): void
    {
        $this->expectException(MethodNotSupported::class);

        $cipherKeyFactory = new OpensslCipherKeyFactory(method: 'foo');
        $cipherKeyFactory();
    }
}
