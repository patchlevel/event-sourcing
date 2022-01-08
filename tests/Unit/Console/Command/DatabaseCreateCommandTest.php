<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand */
final class DatabaseCreateCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testStoreNotSupported(): void
    {
        $helper = $this->prophesize(DoctrineHelper::class);
        $store = $this->prophesize(Store::class);

        $command = new DatabaseCreateCommand(
            $store->reveal(),
            $helper->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] Store is not supported!', $content);
    }

    public function testSuccessful(): void
    {
        $connection = $this->prophesize(Connection::class);

        $helper = $this->prophesize(DoctrineHelper::class);
        $helper->copyConnectionWithoutDatabase($connection)->willReturn($connection);
        $helper->databaseName($connection)->willReturn('test');
        $helper->hasDatabase($connection, 'test')->willReturn(false);
        $helper->createDatabase($connection, 'test')->shouldBeCalled();

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseCreateCommand(
            $store->reveal(),
            $helper->reveal()
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
        $connection = $this->prophesize(Connection::class);

        $helper = $this->prophesize(DoctrineHelper::class);
        $helper->copyConnectionWithoutDatabase($connection)->willReturn($connection);
        $helper->databaseName($connection)->willReturn('test');
        $helper->hasDatabase($connection, 'test')->willReturn(true);

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseCreateCommand(
            $store->reveal(),
            $helper->reveal()
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
        $connection = $this->prophesize(Connection::class);

        $helper = $this->prophesize(DoctrineHelper::class);
        $helper->copyConnectionWithoutDatabase($connection)->willReturn($connection);
        $helper->databaseName($connection)->willReturn('test');
        $helper->hasDatabase($connection, 'test')->willReturn(false);
        $helper->createDatabase($connection, 'test')->willThrow(new RuntimeException('error'));

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseCreateCommand(
            $store->reveal(),
            $helper->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(2, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] Could not create database "test"', $content);
    }
}
