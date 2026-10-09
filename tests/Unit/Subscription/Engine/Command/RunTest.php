<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Command;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Run::class)]
final class RunTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new Run(['foo'], ['bar'], 100);

        self::assertSame(['foo'], $object->ids);
        self::assertSame(['bar'], $object->groups);
        self::assertSame(100, $object->limit);
    }

    public function testInstantiateWithDefaults(): void
    {
        $object = new Run();

        self::assertNull($object->ids);
        self::assertNull($object->groups);
        self::assertNull($object->limit);
    }
}
