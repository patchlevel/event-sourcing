<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Handle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Handle::class)]
final class HandleTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Handle(stdClass::class);

        self::assertSame(stdClass::class, $attribute->commandClass);
    }

    public function testInstantiateWithDefaults(): void
    {
        $attribute = new Handle();

        self::assertNull($attribute->commandClass);
    }
}
