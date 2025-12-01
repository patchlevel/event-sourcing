<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Identifier\CustomId;
use Patchlevel\EventSourcing\Serializer\AttributeEventTagExtractor;
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

    public function testExtractClassWithoutAttributes(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class {
            public function __construct(
                public string $name = 'baz',
            ) {
            }
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

    public function testExtractStringable(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class (new CustomId('foo')) {
            public function __construct(
                #[EventTag]
                public CustomId $id,
            ) {
            }
        };

        $tags = $extractor->extract($event);

        self::assertSame(['foo'], $tags);
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
