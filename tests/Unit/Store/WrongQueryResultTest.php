<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\WrongQueryResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WrongQueryResult::class)]
final class WrongQueryResultTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new WrongQueryResult();

        self::assertSame(
            'the type of the query result is wrong',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
