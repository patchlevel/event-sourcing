<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(DatabaseDropCommand::class)]
final class DatabaseDropCommandTest extends TestCase
{
    public function testMissingForce(): void
    {
        $connection = $this->createMock(Connection::class);

        $helper = $this->createMock(DoctrineHelper::class);
        $helper->method('copyConnectionWithoutDatabase')->with($connection)->willReturn($connection);
        $helper->method('databaseName')->with($connection)->willReturn('test');

        $command = new DatabaseDropCommand(
            $connection,
            $helper,
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(2, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('! [CAUTION] This operation should not be executed in a production environment', $content);
    }

    public function testSuccessful(): void
    {
        $connection = $this->createMock(Connection::class);

        $helper = $this->createMock(DoctrineHelper::class);
        $helper->method('copyConnectionWithoutDatabase')->with($connection)->willReturn($connection);
        $helper->method('hasDatabase')->with($connection, 'test')->willReturn(true);
        $helper->method('databaseName')->with($connection)->willReturn('test');
        $helper->expects($this->atLeastOnce())->method('dropDatabase')->with($connection, 'test');

        $command = new DatabaseDropCommand(
            $connection,
            $helper,
        );

        $input = new ArrayInput(['--force' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] Dropped database "test"', $content);
    }

    public function testSkip(): void
    {
        $connection = $this->createMock(Connection::class);

        $helper = $this->createMock(DoctrineHelper::class);
        $helper->method('copyConnectionWithoutDatabase')->with($connection)->willReturn($connection);
        $helper->method('databaseName')->with($connection)->willReturn('test');
        $helper->method('hasDatabase')->with($connection, 'test')->willReturn(false);

        $command = new DatabaseDropCommand(
            $connection,
            $helper,
        );

        $input = new ArrayInput(['--force' => true, '--if-exists' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[WARNING] Database "test" doesn\'t exist. Skipped.', $content);
    }

    public function testError(): void
    {
        $connection = $this->createMock(Connection::class);

        $helper = $this->createMock(DoctrineHelper::class);
        $helper->method('copyConnectionWithoutDatabase')->with($connection)->willReturn($connection);
        $helper->method('hasDatabase')->with($connection, 'test')->willReturn(true);
        $helper->method('databaseName')->with($connection)->willReturn('test');
        $helper->method('dropDatabase')->with($connection, 'test')->willThrowException(new RuntimeException('error'));

        $command = new DatabaseDropCommand(
            $connection,
            $helper,
        );

        $input = new ArrayInput(['--force' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(3, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] Could not drop database "test"', $content);
    }
}
