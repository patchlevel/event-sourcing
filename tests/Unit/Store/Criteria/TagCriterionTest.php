<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Criteria;

use Patchlevel\EventSourcing\Store\Criteria\TagCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TagCriterion::class)]
final class TagCriterionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new TagCriterion(['foo', 'bar']);

        self::assertSame(['foo', 'bar'], $object->tags);
    }
}
