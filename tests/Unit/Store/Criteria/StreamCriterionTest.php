<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Criteria;

use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamCriterion::class)]
final class StreamCriterionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $criterion = new StreamCriterion('profile-1', 'foo-1');

        self::assertSame(['profile-1', 'foo-1'], $criterion->streamName);
    }

    public function testStartWith(): void
    {
        $criterion = StreamCriterion::startWith('profile');

        self::assertSame(['profile*'], $criterion->streamName);
    }

    public function testAll(): void
    {
        self::assertTrue((new StreamCriterion('*'))->all());
        self::assertTrue((new StreamCriterion('profile-1', '*'))->all());
        self::assertFalse((new StreamCriterion('profile-1'))->all());
        self::assertFalse((new StreamCriterion())->all());
    }
}
