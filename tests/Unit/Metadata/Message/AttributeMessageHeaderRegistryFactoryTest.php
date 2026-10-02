<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message;

use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Metadata\ChainClassLocator;
use Patchlevel\EventSourcing\Metadata\FilesystemClassLocator;
use Patchlevel\EventSourcing\Metadata\InMemoryClassLocator;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\ClassIsNotAHeader;
use Patchlevel\EventSourcing\Metadata\Message\HeaderAlreadyInRegistry;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\StreamStoreHeaderLocator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Message\Fixture\DuplicateFooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Message\Fixture\StreamNameCollisionHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMessageHeaderRegistryFactory::class)]
final class AttributeMessageHeaderRegistryFactoryTest extends TestCase
{
    public function testCreateFromFilesystem(): void
    {
        $registry = (new AttributeMessageHeaderRegistryFactory())->create(
            new FilesystemClassLocator([__DIR__ . '/../../Fixture'], Header::class),
        );

        self::assertSame(
            [
                'baz' => BazHeader::class,
                'foo' => FooHeader::class,
            ],
            $registry->headerClasses(),
        );
    }

    public function testNoHeadersAreRegisteredImplicitly(): void
    {
        $registry = (new AttributeMessageHeaderRegistryFactory())->create(new InMemoryClassLocator([]));

        self::assertSame([], $registry->headerClasses());
    }

    public function testSameClassLocatedTwice(): void
    {
        $registry = (new AttributeMessageHeaderRegistryFactory())->create(
            new InMemoryClassLocator([FooHeader::class, StreamNameHeader::class, FooHeader::class]),
        );

        self::assertSame(
            [
                'foo' => FooHeader::class,
                'streamName' => StreamNameHeader::class,
            ],
            $registry->headerClasses(),
        );
    }

    public function testClassIsNotAHeader(): void
    {
        $this->expectException(ClassIsNotAHeader::class);

        (new AttributeMessageHeaderRegistryFactory())->create(new InMemoryClassLocator([ProfileCreated::class]));
    }

    public function testDuplicateHeaderName(): void
    {
        $this->expectException(HeaderAlreadyInRegistry::class);
        $this->expectExceptionMessage(
            'The header name "foo" is already used by "' . FooHeader::class . '" and cannot be used by "' . DuplicateFooHeader::class . '".',
        );

        (new AttributeMessageHeaderRegistryFactory())->create(
            new InMemoryClassLocator([FooHeader::class, DuplicateFooHeader::class]),
        );
    }

    public function testDuplicateHeaderNameAcrossLocators(): void
    {
        $this->expectException(HeaderAlreadyInRegistry::class);
        $this->expectExceptionMessage(
            'The header name "streamName" is already used by "' . StreamNameHeader::class . '" and cannot be used by "' . StreamNameCollisionHeader::class . '".',
        );

        (new AttributeMessageHeaderRegistryFactory())->create(
            new ChainClassLocator([
                new StreamStoreHeaderLocator(),
                new InMemoryClassLocator([StreamNameCollisionHeader::class]),
            ]),
        );
    }
}
