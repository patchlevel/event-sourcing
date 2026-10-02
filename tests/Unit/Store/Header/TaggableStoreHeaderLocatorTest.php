<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Header;

use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TaggableStoreHeaderLocator;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaggableStoreHeaderLocator::class)]
final class TaggableStoreHeaderLocatorTest extends TestCase
{
    public function testLocate(): void
    {
        self::assertSame(
            [
                StreamNameHeader::class,
                PlayheadHeader::class,
                RecordedOnHeader::class,
                ArchivedHeader::class,
                EventIdHeader::class,
                IndexHeader::class,
                TagsHeader::class,
            ],
            (new TaggableStoreHeaderLocator())->locate(),
        );
    }
}
