<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Identifier\CustomId;
use Patchlevel\EventSourcing\Serializer\AttributeEventTagExtractor;
use Patchlevel\EventSourcing\Serializer\EventTagExtractorError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Stringable;

use function sprintf;

#[CoversClass(AttributeEventTagExtractor::class)]
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

    public function testExtractInt(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class (42) {
            public function __construct(
                #[EventTag]
                public int $number,
            ) {
            }
        };

        $tags = $extractor->extract($event);

        self::assertSame(['42'], $tags);
    }

    public function testExtractRealStringable(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'foo';
            }
        };

        $event = new class ($stringable) {
            public function __construct(
                #[EventTag]
                public Stringable $id,
            ) {
            }
        };

        $tags = $extractor->extract($event);

        self::assertSame(['foo'], $tags);
    }

    public function testExtractInvalidValueType(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class ([]) {
            /** @param list<string> $items */
            public function __construct(
                #[EventTag]
                public array $items,
            ) {
            }
        };

        $this->expectException(EventTagExtractorError::class);
        $this->expectExceptionMessage(
            sprintf(
                'Event tag value for property "items" in class "%s" must be stringable, array given',
                $event::class,
            ),
        );

        $extractor->extract($event);
    }
}
