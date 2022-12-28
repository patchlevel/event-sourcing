<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand;
use Patchlevel\EventSourcing\Schema\DryRunSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand */
final class SchemaUpdateCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $schemaManager = $this->prophesize(SchemaDirector::class);
        $schemaManager->update()->shouldBeCalled();

        $command = new SchemaUpdateCommand(
            $schemaManager->reveal()
        );

        $input = new ArrayInput(['--force' => true]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
        $content = $output->fetch();

        self::assertStringContainsString('[OK] schema updated', $content);
    }

    public function testMissingForce(): void
    {
        $schemaManager = $this->prophesize(SchemaDirector::class);
        $schemaManager->update()->shouldNotBeCalled();

        $command = new SchemaUpdateCommand(
            $schemaManager->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString(
            '[ERROR] Please run the operation with --force to execute. Database could break!',
            $content
        );
    }

    public function testDryRun(): void
    {
        $schemaManager = $this->prophesize(DryRunSchemaDirector::class);
        $schemaManager->dryRunUpdate()->willReturn([
            'update table 1;',
            'update table 2;',
            'update table 3;',
        ]);

        $command = new SchemaUpdateCommand(
            $schemaManager->reveal()
        );

        $input = new ArrayInput(['--dry-run' => true]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
        $content = $output->fetch();

        self::assertStringContainsString('update table 1;', $content);
        self::assertStringContainsString('update table 2;', $content);
        self::assertStringContainsString('update table 3;', $content);
    }

    public function testDryRunNotSupported(): void
    {
        $schemaManager = $this->prophesize(SchemaDirector::class);

        $command = new SchemaUpdateCommand(
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
