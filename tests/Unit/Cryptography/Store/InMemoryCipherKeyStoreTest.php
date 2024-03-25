<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography\Store;

use Patchlevel\EventSourcing\Cryptography\Cipher\CipherKey;
use Patchlevel\EventSourcing\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\EventSourcing\Cryptography\Store\InMemoryCipherKeyStore;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Cryptography\Store\InMemoryCipherKeyStore */
final class InMemoryCipherKeyStoreTest extends TestCase
{
    public function testStoreAndLoad(): void
    {
        $key = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $store = new InMemoryCipherKeyStore();
        $store->store('foo', $key);

        self::assertSame($key, $store->get('foo'));
    }

    public function testLoadFailed(): void
    {
        $this->expectException(CipherKeyNotExists::class);

        $store = new InMemoryCipherKeyStore();
        $store->get('foo');
    }

    public function testRemove(): void
    {
        $key = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $store = new InMemoryCipherKeyStore();
        $store->store('foo', $key);

        self::assertSame($key, $store->get('foo'));

        $store->remove('foo');

        $this->expectException(CipherKeyNotExists::class);

        $store->get('foo');
    }

    public function testClear(): void
    {
        $key = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $store = new InMemoryCipherKeyStore();
        $store->store('foo', $key);

        self::assertSame($key, $store->get('foo'));

        $store->clear();

        $this->expectException(CipherKeyNotExists::class);

        $store->get('foo');
    }
}
