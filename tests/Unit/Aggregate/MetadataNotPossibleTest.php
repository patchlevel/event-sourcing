<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Aggregate\MetadataNotPossible;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Aggregate\MetadataNotPossible */
final class MetadataNotPossibleTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MetadataNotPossible();

        self::assertSame(
            'Metadata method must be called on the concrete implementation',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
