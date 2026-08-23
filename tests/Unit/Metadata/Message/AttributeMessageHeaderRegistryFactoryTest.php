<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMessageHeaderRegistryFactory::class)]
final class AttributeMessageHeaderRegistryFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $registry = (new AttributeMessageHeaderRegistryFactory())->create([
            __DIR__ . '/../../Fixture',
        ]);

        self::assertSame(FooHeader::class, $registry->headerClass('foo'));
        self::assertSame(BazHeader::class, $registry->headerClass('baz'));
        self::assertSame(StreamNameHeader::class, $registry->headerClass('streamName'));
    }
}
