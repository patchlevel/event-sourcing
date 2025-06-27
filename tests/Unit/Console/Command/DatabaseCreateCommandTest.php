<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(DatabaseCreateCommand::class)]
final class DatabaseCreateCommandTest extends TestCase
{
    public function testSuccessful(): void
    {
        $connection = $this->createMock(Connection::class);

        $helper = $this->createMock(DoctrineHelper::class);
        $helper->method('copyConnectionWithoutDatabase')->with($connection)->willReturn($connection);
        $helper->method('databaseName')->with($connection)->willReturn('test');
        $helper->method('hasDatabase')->with($connection, 'test')->willReturn(false);
        $helper->expects($this->atLeastOnce())->method('createDatabase')->with($connection, 'test');

        $command = new DatabaseCreateCommand(
            $connection,
            $helper,
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] Created database "test"', $content);
    }

    public function testSkip(): void
    {
        $connection = $this->createMock(Connection::class);

        $helper = $this->createMock(DoctrineHelper::class);
        $helper->method('copyConnectionWithoutDatabase')->with($connection)->willReturn($connection);
        $helper->method('databaseName')->with($connection)->willReturn('test');
        $helper->method('hasDatabase')->with($connection, 'test')->willReturn(true);

        $command = new DatabaseCreateCommand(
            $connection,
            $helper,
        );

        $input = new ArrayInput(['--if-not-exists' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[WARNING] Database "test" already exists. Skipped.', $content);
    }

    public function testError(): void
    {
        $connection = $this->createMock(Connection::class);

        $helper = $this->createMock(DoctrineHelper::class);
        $helper->method('copyConnectionWithoutDatabase')->with($connection)->willReturn($connection);
        $helper->method('databaseName')->with($connection)->willReturn('test');
        $helper->method('hasDatabase')->with($connection, 'test')->willReturn(false);
        $helper->method('createDatabase')->with($connection, 'test')->willThrowException(new RuntimeException('error'));

        $command = new DatabaseCreateCommand(
            $connection,
            $helper,
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(2, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] Could not create database "test"', $content);
    }
}
