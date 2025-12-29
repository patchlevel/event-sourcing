<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\StoreMigrateCommand;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function iterator_to_array;

#[CoversClass(StoreMigrateCommand::class)]
final class StoreMigrateCommandTest extends TestCase
{
    public function testNoMessages(): void
    {
        $fromStore = new InMemoryStore();
        $toStore = new InMemoryStore();

        $command = new StoreMigrateCommand($fromStore, $toStore, []);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('Migration initialization...', $content);
        self::assertStringContainsString('0', $content);
        self::assertStringContainsString('Migration finished', $content);
    }

    public function testOneMessage(): void
    {
        $fromStore = new InMemoryStore([
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
        ]);
        $toStore = new InMemoryStore();

        $command = new StoreMigrateCommand($fromStore, $toStore, []);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('Migration initialization...', $content);
        self::assertStringContainsString('1', $content);
        self::assertStringContainsString('Migration finished', $content);

        self::assertCount(1, iterator_to_array($toStore->load()->getIterator()));
    }

    public function testTenMessages(): void
    {
        $fromStore = new InMemoryStore([
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('2'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('3'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('4'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('5'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('6'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('7'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('8'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('9'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('10'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
        ]);
        $toStore = new InMemoryStore();

        $command = new StoreMigrateCommand($fromStore, $toStore, []);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('Migration initialization...', $content);
        self::assertStringContainsString('10', $content);
        self::assertStringContainsString('Migration finished', $content);

        self::assertCount(10, iterator_to_array($toStore->load()->getIterator()));
    }

    public function testTenMessagesWithBufferAt2(): void
    {
        $fromStore = new InMemoryStore([
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('2'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('3'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('4'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('5'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('6'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('7'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('8'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('9'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('10'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
        ]);
        $toStore = new InMemoryStore();

        $command = new StoreMigrateCommand($fromStore, $toStore, []);

        $input = new ArrayInput(['--buffer' => 10]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('Migration initialization...', $content);
        self::assertStringContainsString('10', $content);
        self::assertStringContainsString('Migration finished', $content);

        self::assertCount(10, iterator_to_array($toStore->load()->getIterator()));
    }

    public function testTenMessagesWithDroppingTranslator(): void
    {
        $fromStore = new InMemoryStore([
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('2'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('3'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('4'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('5'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('6'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('7'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('8'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileCreated(
                    ProfileId::fromString('9'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
            new Message(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            ),
        ]);
        $toStore = new InMemoryStore();

        $command = new StoreMigrateCommand(
            $fromStore,
            $toStore,
            [new ExcludeEventTranslator([ProfileCreated::class])],
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('Migration initialization...', $content);
        self::assertStringContainsString('10', $content);
        self::assertStringContainsString('Migration finished', $content);

        self::assertCount(1, iterator_to_array($toStore->load()->getIterator()));
    }
}
