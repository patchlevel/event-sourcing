<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\RetryStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryStrategy::class)]
final class RetryStrategyTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new RetryStrategy('foo');

        self::assertSame('foo', $attribute->name);
    }
}
