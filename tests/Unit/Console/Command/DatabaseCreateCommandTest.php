<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class DatabaseCreateCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testStoreNotSupported(): void
    {
        $store = $this->prophesize(Store::class);

        $command = new DatabaseCreateCommand(
            $store->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] Store is not supported!', $content);
    }

    public function testSuccessful(): void
    {
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->listDatabases()->willReturn([]);
        $schemaManager->createDatabase('test')->shouldBeCalled();

        $connection = $this->prophesize(Connection::class);
        $connection->getParams()->willReturn([
            'driverClass' => Driver::class,
            'path' => 'test',
        ]);
        $connection->createSchemaManager()->willReturn($schemaManager);

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseCreateCommand(
            $store->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] Dropped database "test"', $content);
    }

    public function testSkip(): void
    {
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->listDatabases()->willReturn(['test']);
        $schemaManager->createDatabase('test')->shouldBeCalled();

        $connection = $this->prophesize(Connection::class);
        $connection->getParams()->willReturn([
            'driverClass' => Driver::class,
            'path' => 'test',
        ]);
        $connection->createSchemaManager()->willReturn($schemaManager);

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseCreateCommand(
            $store->reveal()
        );

        $input = new ArrayInput(['--if-not-exists' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] Dropped database "test"', $content);
    }
}
