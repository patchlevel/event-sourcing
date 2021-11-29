<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class DatabaseDropCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testStoreNotSupported(): void
    {
        $store = $this->prophesize(Store::class);

        $command = new DatabaseDropCommand(
            $store->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] Store is not supported!', $content);
    }

    public function testMissingForce(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->getParams()->willReturn(['dbname' => 'test']);

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseDropCommand(
            $store->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(2, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] This operation should not be executed in a production environment', $content);
    }

    public function testSuccessful(): void
    {
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->listDatabases()->willReturn(['test']);
        $schemaManager->dropDatabase('test')->shouldBeCalled();

        $connection = $this->prophesize(Connection::class);
        $connection->getParams()->willReturn(['dbname' => 'test']);
        $connection->createSchemaManager()->willReturn($schemaManager);

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseDropCommand(
            $store->reveal()
        );

        $input = new ArrayInput(['--force' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] Dropped database "test"', $content);
    }

    public function testSkip(): void
    {
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->listDatabases()->willReturn([]);

        $connection = $this->prophesize(Connection::class);
        $connection->getParams()->willReturn(['dbname' => 'test']);
        $connection->createSchemaManager()->willReturn($schemaManager);

        $store = $this->prophesize(DoctrineStore::class);
        $store->connection()->willReturn($connection);

        $command = new DatabaseDropCommand(
            $store->reveal()
        );

        $input = new ArrayInput(['--force' => true, '--if-exists' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[WARNING] Database "test" doesn\'t exist. Skipped.', $content);
    }
}
