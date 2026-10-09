<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Subscription\RunMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Projector::class)]
final class ProjectorTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Projector('foo', 'bar', RunMode::Once);

        self::assertSame('foo', $attribute->id);
        self::assertSame('bar', $attribute->group);
        self::assertSame(RunMode::Once, $attribute->runMode);
    }

    public function testInstantiateWithDefaults(): void
    {
        $attribute = new Projector('foo');

        self::assertSame('foo', $attribute->id);
        self::assertSame('projector', $attribute->group);
        self::assertSame(RunMode::FromBeginning, $attribute->runMode);
    }
}
