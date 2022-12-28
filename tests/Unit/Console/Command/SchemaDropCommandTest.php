<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SchemaDropCommand;
use Patchlevel\EventSourcing\Schema\DryRunSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\Command\SchemaDropCommand */
final class SchemaDropCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $schemaManager = $this->prophesize(SchemaDirector::class);
        $schemaManager->drop()->shouldBeCalled();

        $command = new SchemaDropCommand(
            $schemaManager->reveal()
        );

        $input = new ArrayInput(['--force' => true]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
        $content = $output->fetch();

        self::assertStringContainsString('[OK] schema deleted', $content);
    }

    public function testMissingForce(): void
    {
        $schemaManager = $this->prophesize(SchemaDirector::class);
        $schemaManager->drop()->shouldNotBeCalled();

        $command = new SchemaDropCommand(
            $schemaManager->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString(
            '[ERROR] Please run the operation with --force to execute. All data will be lost!',
            $content
        );
    }

    public function testDryRun(): void
    {
        $schemaManager = $this->prophesize(DryRunSchemaDirector::class);
        $schemaManager->dryRunDrop()->willReturn([
            'drop table 1;',
            'drop table 2;',
            'drop table 3;',
        ]);

        $command = new SchemaDropCommand(
            $schemaManager->reveal()
        );

        $input = new ArrayInput(['--dry-run' => true]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('drop table 1;', $content);
        self::assertStringContainsString('drop table 2;', $content);
        self::assertStringContainsString('drop table 3;', $content);
    }

    public function testDryRunNotSupported(): void
    {
        $schemaManager = $this->prophesize(SchemaDirector::class);

        $command = new SchemaDropCommand(
            $schemaManager->reveal()
        );

        $input = new ArrayInput(['--dry-run' => true]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] SchemaDirector dont support dry-run', $content);
    }
}
