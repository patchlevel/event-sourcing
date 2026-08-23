<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Countable;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Projection\ApplyMethodDetectionError;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Stringable;

#[CoversClass(BasicProjection::class)]
final class BasicProjectionTest extends TestCase
{
    public function testApplyWithMatchingEvent(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return ['match'];
            }

            #[Apply]
            public function applyProfileCreated(int $state, ProfileCreated $event): int
            {
                return $state + 1;
            }
        };

        $state = $projection->apply(0, $this->message(['match']));

        self::assertSame(1, $state);
        self::assertEquals(
            new SubQuery(['match'], [ProfileCreated::class]),
            $projection->subQuery(),
        );
        self::assertSame($projection->subQuery(), $projection->subQuery());
    }

    public function testApplyWithNonMatchingMessage(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return ['match'];
            }

            #[Apply]
            public function applyProfileCreated(int $state, ProfileCreated $event): int
            {
                return $state + 1;
            }
        };

        self::assertSame(0, $projection->apply(0, $this->message(['other'])));
    }

    public function testApplyWithUnhandledEvent(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            /** @return list<class-string> */
            protected function eventTypeFilter(): array
            {
                return [];
            }

            #[Apply]
            public function applyProfileVisited(int $state, ProfileVisited $event): int
            {
                return $state + 1;
            }
        };

        self::assertSame(0, $projection->apply(0, $this->message([])));
    }

    public function testApplyWithExplicitEventClass(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            #[Apply(ProfileCreated::class)]
            public function applyProfileCreated(int $state, object $event): int
            {
                return $state + 1;
            }
        };

        self::assertSame(1, $projection->apply(0, $this->message([])));
    }

    public function testApplyWithUnionType(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            #[Apply]
            public function applyEvent(int $state, ProfileCreated|ProfileVisited $event): int
            {
                return $state + 1;
            }
        };

        self::assertEquals(
            new SubQuery([], [ProfileCreated::class, ProfileVisited::class]),
            $projection->subQuery(),
        );
        self::assertSame(1, $projection->apply(0, $this->message([])));
    }

    public function testDuplicateEmptyApplyAttribute(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            #[Apply]
            #[Apply]
            public function applyEvent(int $state, ProfileCreated $event): int
            {
                return $state;
            }
        };

        $this->expectException(ApplyMethodDetectionError::class);

        $projection->subQuery();
    }

    public function testMixedApplyAttributeUsage(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            #[Apply]
            #[Apply(ProfileVisited::class)]
            public function applyEvent(int $state, ProfileCreated $event): int
            {
                return $state;
            }
        };

        $this->expectException(ApplyMethodDetectionError::class);

        $projection->subQuery();
    }

    public function testApplyTypeIsNotAClass(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            #[Apply]
            public function applyEvent(int $state, string $event): int
            {
                return $state;
            }
        };

        $this->expectException(ApplyMethodDetectionError::class);

        $projection->subQuery();
    }

    public function testDuplicateApplyMethod(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            #[Apply]
            public function applyA(int $state, ProfileCreated $event): int
            {
                return $state;
            }

            #[Apply]
            public function applyB(int $state, ProfileCreated $event): int
            {
                return $state;
            }
        };

        $this->expectException(ApplyMethodDetectionError::class);

        $projection->subQuery();
    }

    public function testParameterIsMissing(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            #[Apply]
            public function applyEvent(int $state): int
            {
                return $state;
            }
        };

        $this->expectException(ApplyMethodDetectionError::class);

        $projection->subQuery();
    }

    public function testUntypedParameter(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            // phpcs:disable
            /** @phpstan-ignore-next-line */
            #[Apply]
            public function applyEvent(int $state, $event): int
            {
                return $state;
            }
            // phpcs:enable
        };

        $this->expectException(ApplyMethodDetectionError::class);

        $projection->subQuery();
    }

    public function testIntersectionType(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            #[Apply]
            public function applyEvent(int $state, Countable&Stringable $event): int
            {
                return $state;
            }
        };

        $this->expectException(ApplyMethodDetectionError::class);

        $projection->subQuery();
    }

    public function testUnionWithIntersectionType(): void
    {
        $projection = new class extends BasicProjection {
            public function initialState(): int
            {
                return 0;
            }

            /** @return list<string> */
            protected function tagFilter(): array
            {
                return [];
            }

            #[Apply]
            public function applyEvent(int $state, ProfileCreated|(Countable&Stringable) $event): int
            {
                return $state;
            }
        };

        $this->expectException(ApplyMethodDetectionError::class);

        $projection->subQuery();
    }

    /** @param list<string> $tags */
    private function message(array $tags): Message
    {
        return Message::create(new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com'),
        ))->withHeader(new TagsHeader($tags));
    }
}
