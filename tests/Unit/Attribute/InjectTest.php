<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Inject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Inject::class)]
final class InjectTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Inject('foo');

        self::assertSame('foo', $attribute->service);
    }
}
