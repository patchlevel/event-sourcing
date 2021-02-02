<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\SchemaDropCommand;
use Patchlevel\EventSourcing\Schema\DryRunSchemaManager;
use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Store\Store;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SchemaDropCommandTest extends TestCase
{
    use ProphecyTrait;
    use MatchesSnapshots;

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
        self::assertMatchesSnapshot($output->fetch());
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
        self::assertMatchesSnapshot($output->fetch());
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
        self::assertMatchesSnapshot($output->fetch());
    }
}
