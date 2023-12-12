<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand */
final class DatabaseDropCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testStoreNotSupported(): void
    {
        $store = $this->prophesize(Store::class);
        $helper = $this->prophesize(DoctrineHelper::class);

        $command = new DatabaseDropCommand(
            $store->reveal(),
            $helper->reveal(),
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] Store is not supported!', $content);
    }

    public function testMissingForce(): void
    {
        $connection = $this->prophesize(Connection::class);

        $helper = $this->prophesize(DoctrineHelper::class);
        $helper->copyConnectionWithoutDatabase($connection)->willReturn($connection);
        $helper->databaseName($connection)->willReturn('test');

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseDropCommand(
            $store->reveal(),
            $helper->reveal(),
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
        $connection = $this->prophesize(Connection::class);

        $helper = $this->prophesize(DoctrineHelper::class);
        $helper->copyConnectionWithoutDatabase($connection)->willReturn($connection);
        $helper->hasDatabase($connection, 'test')->willReturn(true);
        $helper->databaseName($connection)->willReturn('test');
        $helper->dropDatabase($connection, 'test')->shouldBeCalled();

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseDropCommand(
            $store->reveal(),
            $helper->reveal(),
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
        $connection = $this->prophesize(Connection::class);

        $helper = $this->prophesize(DoctrineHelper::class);
        $helper->copyConnectionWithoutDatabase($connection)->willReturn($connection);
        $helper->databaseName($connection)->willReturn('test');
        $helper->hasDatabase($connection, 'test')->willReturn(false);

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseDropCommand(
            $store->reveal(),
            $helper->reveal(),
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
        $connection = $this->prophesize(Connection::class);

        $helper = $this->prophesize(DoctrineHelper::class);
        $helper->copyConnectionWithoutDatabase($connection)->willReturn($connection);
        $helper->hasDatabase($connection, 'test')->willReturn(true);
        $helper->databaseName($connection)->willReturn('test');
        $helper->dropDatabase($connection, 'test')->willThrow(new RuntimeException('error'));

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseDropCommand(
            $store->reveal(),
            $helper->reveal(),
        );

        $input = new ArrayInput(['--force' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(3, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] Could not drop database "test"', $content);
    }
}
