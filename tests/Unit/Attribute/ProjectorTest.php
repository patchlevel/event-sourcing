<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Projector;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Attribute\Projector */
final class ProjectorTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Projector('foo');

        self::assertSame('foo', $attribute->id);
    }
}
