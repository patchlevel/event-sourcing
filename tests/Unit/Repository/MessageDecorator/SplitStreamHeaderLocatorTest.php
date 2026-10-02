<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamHeaderLocator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\StreamStartHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SplitStreamHeaderLocator::class)]
final class SplitStreamHeaderLocatorTest extends TestCase
{
    public function testLocate(): void
    {
        self::assertSame([StreamStartHeader::class], (new SplitStreamHeaderLocator())->locate());
    }
}
