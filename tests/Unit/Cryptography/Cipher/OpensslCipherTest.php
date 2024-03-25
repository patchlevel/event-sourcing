<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography\Cipher;

use Generator;
use Patchlevel\EventSourcing\Cryptography\Cipher\CipherKey;
use Patchlevel\EventSourcing\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\EventSourcing\Cryptography\Cipher\EncryptionFailed;
use Patchlevel\EventSourcing\Cryptography\Cipher\OpensslCipher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Cryptography\Cipher\OpensslCipher */
final class OpensslCipherTest extends TestCase
{
    #[DataProvider('dataProvider')]
    public function testEncrypt(mixed $value, string $encryptedString): void
    {
        $cipher = new OpensslCipher();
        $return = $cipher->encrypt($this->createKey(), $value);

        self::assertEquals($encryptedString, $return);
    }

    public function testEncryptFailed(): void
    {
        $this->expectException(EncryptionFailed::class);

        $cipher = new OpensslCipher();
        $cipher->encrypt(new CipherKey(
            'key',
            'bar',
            'abcdefg123456789',
        ), '');
    }

    #[DataProvider('dataProvider')]
    public function testDecrypt(mixed $value, string $encryptedString): void
    {
        $cipher = new OpensslCipher();
        $return = $cipher->decrypt($this->createKey(), $encryptedString);

        self::assertEquals($value, $return);
    }

    public function testDecryptFailed(): void
    {
        $this->expectException(DecryptionFailed::class);

        $cipher = new OpensslCipher();
        $cipher->decrypt($this->createKey('foo'), 'emNpWDlMWFBnRStpZk9YZktrUStRQT09');
    }

    public static function dataProvider(): Generator
    {
        yield 'empty' => ['', 'emNpWDlMWFBnRStpZk9YZktrUStRQT09'];
        yield 'string' => ['foo bar baz', 'YUlYRnJZMEd1RkFycjNrQitETHhqQT09'];
        yield 'integer' => [42, 'M1FHSnlnbWNlZFJiV2xwdzZIZUhDdz09'];
        yield 'float' => [0.5, 'N2tOWGNia3lrdUJ1ancrMFA4OEY0Zz09'];
        yield 'null' => [null, 'OUE1T081cXdpNmFMc1FIMGsrME5vdz09'];
        yield 'true' => [true, 'NCtWMDE4WnV5NEtCamVVdkIxZjRrdz09'];
        yield 'false' => [false, 'czh5NUYxWXhQOWhSbGVwWG5ETFdVQT09'];
        yield 'array' => [['foo' => 'bar'], 'cHo2QlhxSnNFZG1kUEhRZ3pjcFJrUT09'];
        yield 'long text' => ['Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.', 'eDNCalYzSS9LbkZIcGdKNWVmUFQwTTI0YXhhSnNmdUxXeXhGUGFwMWZkTmx1ZnNwNzBUa29NcUFxUzRFV3V2WWNlUmt6YWhTSlRzVXpqd3RLZkpzUWFWYVRCR1pvbkt3TUE4UzZmaDVQcTYzMzJoWVBRRzllbHhhNjYrenNWbzFDZ2lnVm1PRFhvamozZEVmcXFYVTZGQ1dIWEgzcE1mU2w2SWlRQ2o2WFdNPQ=='];
    }

    /** @param non-empty-string $key */
    private function createKey(string $key = 'key'): CipherKey
    {
        return new CipherKey(
            $key,
            'aes128',
            'abcdefg123456789',
        );
    }
}
