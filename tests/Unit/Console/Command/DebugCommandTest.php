<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\DebugCommand;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\Command\DebugCommand */
final class DebugCommandTest extends TestCase
{
    public function testSuccessful(): void
    {
        $command = new DebugCommand(
            new AggregateRootRegistry(['profile' => Profile::class]),
            new EventRegistry(['profile.created' => ProfileCreated::class]),
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('profile', $content);
        self::assertStringContainsString(Profile::class, $content);
        self::assertStringContainsString('profile.created', $content);
        self::assertStringContainsString(ProfileCreated::class, $content);
    }
}
