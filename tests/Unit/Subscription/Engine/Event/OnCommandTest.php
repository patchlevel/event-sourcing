<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnCommand::class)]
final class OnCommandTest extends TestCase
{
    public function testInstantiate(): void
    {
        $command = new Run();

        $event = new OnCommand($command);

        self::assertSame($command, $event->command);
    }
}
