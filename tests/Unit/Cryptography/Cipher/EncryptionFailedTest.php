<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography\Cipher;

use Patchlevel\EventSourcing\Cryptography\Cipher\EncryptionFailed;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Cryptography\Cipher\EncryptionFailed */
final class EncryptionFailedTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new EncryptionFailed();

        self::assertSame('Encryption failed.', $exception->getMessage());
    }
}
