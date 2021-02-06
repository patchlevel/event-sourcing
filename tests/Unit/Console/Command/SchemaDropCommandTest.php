<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SchemaDropCommand;
use Patchlevel\EventSourcing\Schema\DryRunSchemaManager;
use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Store\Store;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SchemaDropCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $store = $this->prophesize(Store::class)->reveal();

        $schemaManager = $this->prophesize(SchemaManager::class);
        $schemaManager->drop($store)->shouldBeCalled();

        $command = new SchemaDropCommand(
            $store,
            $schemaManager->reveal()
        );

        $input = new ArrayInput(['--force' => true]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);
        $content = $output->fetch();

        self::assertStringContainsString('[OK] schema deleted', $content);
    }

    public function testMissingForce(): void
    {
        $store = $this->prophesize(Store::class)->reveal();

        $schemaManager = $this->prophesize(SchemaManager::class);
        $schemaManager->drop($store)->shouldNotBeCalled();

        $command = new SchemaDropCommand(
            $store,
            $schemaManager->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString(
            '[ERROR] Please run the operation with --force to execute. All data will be lost!',
            $content
        );
    }

    public function testDryRun(): void
    {
        $store = $this->prophesize(Store::class)->reveal();

        $schemaManager = $this->prophesize(DryRunSchemaManager::class);
        $schemaManager->dryRunDrop($store)->willReturn([
            'drop table 1;',
            'drop table 2;',
            'drop table 3;',
        ]);

        $command = new SchemaDropCommand(
            $store,
            $schemaManager->reveal()
        );

        $input = new ArrayInput(['--dry-run' => true]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('drop table 1;', $content);
        self::assertStringContainsString('drop table 2;', $content);
        self::assertStringContainsString('drop table 3;', $content);
    }

    public function testDryRunNotSupported(): void
    {
        $store = $this->prophesize(Store::class)->reveal();

        $schemaManager = $this->prophesize(SchemaManager::class);

        $command = new SchemaDropCommand(
            $store,
            $schemaManager->reveal()
        );

        $input = new ArrayInput(['--dry-run' => true]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] SchemaManager dont support dry-run', $content);
    }
}
