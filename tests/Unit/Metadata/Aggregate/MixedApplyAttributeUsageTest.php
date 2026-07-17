<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\MixedApplyAttributeUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MixedApplyAttributeUsage::class)]
final class MixedApplyAttributeUsageTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MixedApplyAttributeUsage('apply');

        self::assertSame(
            'The method [apply] has at least one apply attribute with an event name and one without which is not allowed.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
