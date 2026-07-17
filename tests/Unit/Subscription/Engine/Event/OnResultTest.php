<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnResult::class)]
final class OnResultTest extends TestCase
{
    public function testInstantiate(): void
    {
        $command = new Run();
        $result = new Result();

        $event = new OnResult($command, $result);

        self::assertSame($command, $event->command);
        self::assertSame($result, $event->result);
    }
}
