<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography\Cipher;

use Patchlevel\EventSourcing\Cryptography\Cipher\DecryptionFailed;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Cryptography\Cipher\DecryptionFailed */
final class DecryptionFailedTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new DecryptionFailed();

        self::assertSame('Decryption failed.', $exception->getMessage());
    }
}
