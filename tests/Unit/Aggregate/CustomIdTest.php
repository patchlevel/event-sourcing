<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Aggregate\CustomId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Aggregate\CustomId */
final class CustomIdTest extends TestCase
{
    public function testFromString(): void
    {
        $id = CustomId::fromString('1');

        self::assertSame('1', $id->toString());
    }
}
