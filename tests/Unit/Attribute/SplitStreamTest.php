<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\SplitStream;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Attribute\SplitStream */
final class SplitStreamTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testInstantiate(): void
    {
        $attribute = new SplitStream();
    }
}
