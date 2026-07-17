<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Command;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Setup::class)]
final class SetupTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new Setup(['foo'], ['bar'], true);

        self::assertSame(['foo'], $object->ids);
        self::assertSame(['bar'], $object->groups);
        self::assertTrue($object->skipBooting);
    }

    public function testInstantiateWithDefaults(): void
    {
        $object = new Setup();

        self::assertNull($object->ids);
        self::assertNull($object->groups);
        self::assertFalse($object->skipBooting);
    }
}
