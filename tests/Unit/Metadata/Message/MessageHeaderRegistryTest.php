<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\Message\HeaderClassNotRegistered;
use Patchlevel\EventSourcing\Metadata\Message\HeaderNameNotRegistered;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageHeaderRegistry::class)]
final class MessageHeaderRegistryTest extends TestCase
{
    public function testHeaderName(): void
    {
        $registry = new MessageHeaderRegistry(['foo' => FooHeader::class]);

        self::assertSame('foo', $registry->headerName(FooHeader::class));
    }

    public function testHeaderNameNotRegistered(): void
    {
        $registry = new MessageHeaderRegistry(['foo' => FooHeader::class]);

        $this->expectException(HeaderClassNotRegistered::class);

        $registry->headerName(BazHeader::class);
    }

    public function testHeaderClass(): void
    {
        $registry = new MessageHeaderRegistry(['foo' => FooHeader::class]);

        self::assertSame(FooHeader::class, $registry->headerClass('foo'));
    }

    public function testHeaderClassNotRegistered(): void
    {
        $registry = new MessageHeaderRegistry(['foo' => FooHeader::class]);

        $this->expectException(HeaderNameNotRegistered::class);

        $registry->headerClass('baz');
    }

    public function testHasHeaderClass(): void
    {
        $registry = new MessageHeaderRegistry(['foo' => FooHeader::class]);

        self::assertTrue($registry->hasHeaderClass(FooHeader::class));
        self::assertFalse($registry->hasHeaderClass(BazHeader::class));
    }

    public function testHasHeaderName(): void
    {
        $registry = new MessageHeaderRegistry(['foo' => FooHeader::class]);

        self::assertTrue($registry->hasHeaderName('foo'));
        self::assertFalse($registry->hasHeaderName('baz'));
    }

    public function testHeaderClassesAndNames(): void
    {
        $registry = new MessageHeaderRegistry(['foo' => FooHeader::class]);

        self::assertSame(['foo' => FooHeader::class], $registry->headerClasses());
        self::assertSame([FooHeader::class => 'foo'], $registry->headerNames());
    }
}
