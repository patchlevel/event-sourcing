<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\HeaderAlreadyInRegistry;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMessageHeaderRegistryFactory::class)]
final class AttributeMessageHeaderRegistryFactoryTest extends TestCase
{
    public function testCreateRegistry(): void
    {
        $factory = new AttributeMessageHeaderRegistryFactory();
        $registry = $factory->create([__DIR__ . '/../../Fixture/Header']);

        self::assertTrue($registry->hasHeaderClass(FooHeader::class));
        self::assertTrue($registry->hasHeaderClass(BazHeader::class));
        self::assertTrue($registry->hasHeaderClass(ArchivedHeader::class));
        self::assertSame('foo', $registry->headerName(FooHeader::class));
        self::assertSame(FooHeader::class, $registry->headerClass('foo'));
        self::assertSame(FooHeader::class, $registry->headerClass('legacyFoo'));
    }

    public function testCreateRegistryWithDuplicateAlias(): void
    {
        $this->expectException(HeaderAlreadyInRegistry::class);
        $this->expectExceptionMessage('The header name "legacy" is already used in the registry. Maybe you defined an alias which is already used as header name or alias.');

        $factory = new AttributeMessageHeaderRegistryFactory();
        $factory->create([__DIR__ . '/Fixture/DuplicateAlias']);
    }

    public function testCreateRegistryWithAliasUsedAsHeaderName(): void
    {
        $this->expectException(HeaderAlreadyInRegistry::class);
        $this->expectExceptionMessage('The header name "application" is already used in the registry. Maybe you defined an alias which is already used as header name or alias.');

        $factory = new AttributeMessageHeaderRegistryFactory();
        $factory->create([__DIR__ . '/Fixture/AliasUsedAsName']);
    }

    public function testCreateRegistryWithAliasUsedAsInternalHeaderName(): void
    {
        $this->expectException(HeaderAlreadyInRegistry::class);
        $this->expectExceptionMessage('The header name "archived" is already used in the registry. Maybe you defined an alias which is already used as header name or alias.');

        $factory = new AttributeMessageHeaderRegistryFactory();
        $factory->create([__DIR__ . '/Fixture/AliasUsedAsInternalName']);
    }
}
