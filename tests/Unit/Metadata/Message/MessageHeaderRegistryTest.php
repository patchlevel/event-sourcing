<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\Message\HeaderClassNotRegistered;
use Patchlevel\EventSourcing\Metadata\Message\HeaderNameNotRegistered;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageHeaderRegistry::class)]
final class MessageHeaderRegistryTest extends TestCase
{
    public function testEmpty(): void
    {
        $registry = new MessageHeaderRegistry([]);

        self::assertFalse($registry->hasHeaderClass(FooHeader::class));
        self::assertCount(0, $registry->headerClasses());
    }

    public function testMapping(): void
    {
        $registry = new MessageHeaderRegistry(['foo' => FooHeader::class]);

        self::assertTrue($registry->hasHeaderClass(FooHeader::class));
        self::assertTrue($registry->hasHeaderName('foo'));
        self::assertSame('foo', $registry->headerName(FooHeader::class));
        self::assertSame(FooHeader::class, $registry->headerClass('foo'));
        self::assertSame(['foo' => FooHeader::class], $registry->headerClasses());
        self::assertSame([FooHeader::class => 'foo'], $registry->headerNames());
    }

    public function testMappingWithAliases(): void
    {
        $registry = new MessageHeaderRegistry([
            'foo' => FooHeader::class,
            'legacyFoo' => FooHeader::class,
        ]);

        self::assertTrue($registry->hasHeaderClass(FooHeader::class));
        self::assertTrue($registry->hasHeaderName('foo'));
        self::assertTrue($registry->hasHeaderName('legacyFoo'));
        self::assertSame('foo', $registry->headerName(FooHeader::class));
        self::assertSame(FooHeader::class, $registry->headerClass('foo'));
        self::assertSame(FooHeader::class, $registry->headerClass('legacyFoo'));
        self::assertSame([
            'foo' => FooHeader::class,
            'legacyFoo' => FooHeader::class,
        ], $registry->headerClasses());
        self::assertSame([FooHeader::class => 'foo'], $registry->headerNames());
    }

    public function testHeaderClassNotRegistered(): void
    {
        $this->expectException(HeaderClassNotRegistered::class);

        $registry = new MessageHeaderRegistry([]);
        $registry->headerName(FooHeader::class);
    }

    public function testHeaderNameNotRegistered(): void
    {
        $this->expectException(HeaderNameNotRegistered::class);

        $registry = new MessageHeaderRegistry([]);
        $registry->headerClass('foo');
    }
}
