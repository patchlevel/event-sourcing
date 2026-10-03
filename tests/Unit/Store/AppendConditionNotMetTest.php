<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AppendConditionNotMet::class)]
final class AppendConditionNotMetTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AppendConditionNotMet($condition = new AppendCondition());

        self::assertSame(
            'Append condition not met',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertSame($condition, $exception->appendCondition);
    }
}
