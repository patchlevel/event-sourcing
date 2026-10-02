<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message;

use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Metadata\FilesystemClassLocator;
use Patchlevel\EventSourcing\Metadata\InMemoryClassLocator;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\ClassIsNotAHeader;
use Patchlevel\EventSourcing\Metadata\Message\HeaderAlreadyInRegistry;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Message\Fixture\DuplicateFooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Message\Fixture\ReservedNameHeader;
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

        self::assertSame(FooHeader::class, $registry->headerClass('foo'));
        self::assertSame(BazHeader::class, $registry->headerClass('baz'));
        self::assertSame(StreamNameHeader::class, $registry->headerClass('streamName'));
    }

    public function testInternalHeadersAreAlwaysRegistered(): void
    {
        $registry = (new AttributeMessageHeaderRegistryFactory())->create(new InMemoryClassLocator([]));

        self::assertSame(
            [
                'streamName' => StreamNameHeader::class,
                'playhead' => PlayheadHeader::class,
                'recordedOn' => RecordedOnHeader::class,
                'archived' => ArchivedHeader::class,
                'newStreamStart' => StreamStartHeader::class,
                'eventId' => EventIdHeader::class,
                'index' => IndexHeader::class,
                'tags' => TagsHeader::class,
            ],
            $registry->headerClasses(),
        );
    }

    public function testSameClassLocatedTwice(): void
    {
        $registry = (new AttributeMessageHeaderRegistryFactory())->create(
            new InMemoryClassLocator([FooHeader::class, FooHeader::class, StreamNameHeader::class]),
        );

        self::assertSame('foo', $registry->headerName(FooHeader::class));
        self::assertSame('streamName', $registry->headerName(StreamNameHeader::class));
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

    public function testReservedHeaderName(): void
    {
        $this->expectException(HeaderAlreadyInRegistry::class);
        $this->expectExceptionMessage(
            'The header name "streamName" is already used by "' . StreamNameHeader::class . '" and cannot be used by "' . ReservedNameHeader::class . '".',
        );

        (new AttributeMessageHeaderRegistryFactory())->create(
            new InMemoryClassLocator([ReservedNameHeader::class]),
        );
    }
}
