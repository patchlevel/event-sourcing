<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\DCB;

use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\DCB\AttributeEventTagExtractor;
use PHPUnit\Framework\TestCase;

final class AttributeEventTagExtractorTest extends TestCase
{
    public function testExtractEmpty(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class {
        };

        $tags = $extractor->extract($event);

        self::assertSame([], $tags);
    }

    public function testExtract(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class ('foo') {
            public function __construct(
                #[EventTag]
                public string $id,
                #[EventTag(prefix: 'bar')]
                public string $name = 'baz',
            ) {
            }
        };

        $tags = $extractor->extract($event);

        self::assertSame(['foo', 'bar:baz'], $tags);
    }

    public function testExtractWithHash(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class ('foo') {
            public function __construct(
                #[EventTag(prefix: 'name', hash: 'sha1')]
                public string $name,
            ) {
            }
        };

        $tags = $extractor->extract($event);

        self::assertSame(['name:0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33'], $tags);
    }
}
