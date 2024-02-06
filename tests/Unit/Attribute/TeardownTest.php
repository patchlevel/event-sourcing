<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Teardown;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Attribute\Teardown */
final class TeardownTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testInstantiate(): void
    {
        $attribute = new Teardown();
    }
}
