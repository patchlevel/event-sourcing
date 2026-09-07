<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\Criteria\TagCriterion;
use Patchlevel\EventSourcing\Store\UnsupportedCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(UnsupportedCriterion::class)]
final class UnsupportedCriterionTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new UnsupportedCriterion(TagCriterion::class);

        self::assertSame(
            sprintf('criterion %s not supported', TagCriterion::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
