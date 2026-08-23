<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Criteria;

use Patchlevel\EventSourcing\Store\Criteria\CriterionNotFound;
use Patchlevel\EventSourcing\Store\Criteria\TagCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(CriterionNotFound::class)]
final class CriterionNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new CriterionNotFound(TagCriterion::class);

        self::assertSame(
            sprintf('criterion %s not found', TagCriterion::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
