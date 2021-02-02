<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\SchemaUpdateCommand;
use Patchlevel\EventSourcing\Schema\DryRunSchemaManager;
use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Store\Store;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SchemaUpdateCommandTest extends TestCase
{
    use ProphecyTrait;
    use MatchesSnapshots;

    public function testSuccessful(): void
    {
        $store = $this->prophesize(Store::class)->reveal();

        $schemaManager = $this->prophesize(SchemaManager::class);
        $schemaManager->update($store)->shouldBeCalled();

        $command = new SchemaUpdateCommand(
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
        $schemaManager->update($store)->shouldNotBeCalled();

        $command = new SchemaUpdateCommand(
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
        $schemaManager->dryRunUpdate($store)->willReturn([
            'update table 1;',
            'update table 2;',
            'update table 3;',
        ]);

        $command = new SchemaUpdateCommand(
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
