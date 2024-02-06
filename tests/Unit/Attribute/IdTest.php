<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Id;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Attribute\Id */
final class IdTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testInstantiate(): void
    {
        $attribute = new Id();
    }
}
