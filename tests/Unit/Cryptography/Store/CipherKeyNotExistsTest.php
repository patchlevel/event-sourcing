<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography\Store;

use Patchlevel\EventSourcing\Cryptography\Store\CipherKeyNotExists;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Cryptography\Store\CipherKeyNotExists */
final class CipherKeyNotExistsTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new CipherKeyNotExists('foo');

        self::assertSame('Cipher key with subject id "foo" not found.', $exception->getMessage());
    }
}
