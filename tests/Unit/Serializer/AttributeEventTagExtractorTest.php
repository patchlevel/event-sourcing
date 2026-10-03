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

use function hash;
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

    public function testExtractNullIsSkipped(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class (null) {
            public function __construct(
                #[EventTag]
                public string|null $id,
            ) {
            }
        };

        $tags = $extractor->extract($event);

        self::assertSame([], $tags);
    }

    public function testExtractNullIsSkippedButOtherTagsRemain(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class ('foo', null) {
            public function __construct(
                #[EventTag]
                public string $id,
                #[EventTag(prefix: 'guest')]
                public string|null $guestName,
            ) {
            }
        };

        $tags = $extractor->extract($event);

        self::assertSame(['foo'], $tags);
    }

    public function testExtractArray(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class (['1', 2, new CustomId('3'), null, '1']) {
            /** @param list<string|int|CustomId|null> $productIds */
            public function __construct(
                #[EventTag(prefix: 'product')]
                public array $productIds,
            ) {
            }
        };

        $tags = $extractor->extract($event);

        self::assertSame(['product:1', 'product:2', 'product:3'], $tags);
    }

    public function testExtractArrayWithHash(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class (['foo', 'bar']) {
            /** @param list<string> $emails */
            public function __construct(
                #[EventTag(prefix: 'email', hash: 'sha256')]
                public array $emails,
            ) {
            }
        };

        $tags = $extractor->extract($event);

        self::assertSame(
            ['email:' . hash('sha256', 'foo'), 'email:' . hash('sha256', 'bar')],
            $tags,
        );
    }

    public function testExtractEmptyArray(): void
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

        self::assertSame([], $extractor->extract($event));
    }

    public function testExtractNestedArrayIsInvalid(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class ([['foo']]) {
            /** @param list<list<string>> $items */
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

    public function testExtractInvalidValueType(): void
    {
        $extractor = new AttributeEventTagExtractor();

        $event = new class (1.5) {
            public function __construct(
                #[EventTag]
                public float $value,
            ) {
            }
        };

        $this->expectException(EventTagExtractorError::class);
        $this->expectExceptionMessage(
            sprintf(
                'Event tag value for property "value" in class "%s" must be stringable, float given',
                $event::class,
            ),
        );

        $extractor->extract($event);
    }
}
