<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\SchemaCreateCommand;
use Patchlevel\EventSourcing\Schema\DryRunSchemaManager;
use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Store\Store;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SchemaCreateCommandTest extends TestCase
{
    use ProphecyTrait;
    use MatchesSnapshots;

    public function testSuccessful(): void
    {
        $store = $this->prophesize(Store::class)->reveal();

        $schemaManager = $this->prophesize(SchemaManager::class);
        $schemaManager->create($store)->shouldBeCalled();

        $command = new SchemaCreateCommand(
            $store,
            $schemaManager->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);
        self::assertMatchesSnapshot($output->fetch());
    }

    public function testDryRun(): void
    {
        $store = $this->prophesize(Store::class)->reveal();

        $schemaManager = $this->prophesize(DryRunSchemaManager::class);
        $schemaManager->dryRunCreate($store)->willReturn([
            'create table 1;',
            'create table 2;',
            'create table 3;',
        ]);

        $command = new SchemaCreateCommand(
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
