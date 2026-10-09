<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\AlreadyProcessing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlreadyProcessing::class)]
final class AlreadyProcessingTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AlreadyProcessing();

        self::assertSame(
            'Subscription engine is already processing',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
