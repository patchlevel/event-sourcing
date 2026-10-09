<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Subscription\RunMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Processor::class)]
final class ProcessorTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Processor('foo', 'bar', RunMode::FromBeginning);

        self::assertSame('foo', $attribute->id);
        self::assertSame('bar', $attribute->group);
        self::assertSame(RunMode::FromBeginning, $attribute->runMode);
    }

    public function testInstantiateWithDefaults(): void
    {
        $attribute = new Processor('foo');

        self::assertSame('foo', $attribute->id);
        self::assertSame('processor', $attribute->group);
        self::assertSame(RunMode::FromNow, $attribute->runMode);
    }
}
