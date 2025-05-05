<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand;
use Patchlevel\EventSourcing\Schema\DryRunSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(SchemaUpdateCommand::class)]
final class SchemaUpdateCommandTest extends TestCase
{
    public function testSuccessful(): void
    {
        $schemaManager = $this->createMock(SchemaDirector::class);
        $schemaManager->expects($this->atLeastOnce())->method('update');

        $command = new SchemaUpdateCommand(
            $schemaManager,
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
        $schemaManager = $this->createMock(SchemaDirector::class);
        $schemaManager->expects($this->never())->method('update');

        $command = new SchemaUpdateCommand(
            $schemaManager,
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString(
            '[ERROR] Please run the operation with --force to execute. Database could break!',
            $content,
        );
    }

    public function testDryRun(): void
    {
        $schemaManager = $this->createMock(DryRunSchemaDirector::class);
        $schemaManager->method('dryRunUpdate')->willReturn([
            'update table 1;',
            'update table 2;',
            'update table 3;',
        ]);

        $command = new SchemaUpdateCommand(
            $schemaManager,
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
        $schemaManager = $this->createMock(SchemaDirector::class);

        $command = new SchemaUpdateCommand(
            $schemaManager,
        );

        $input = new ArrayInput(['--dry-run' => true]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] SchemaDirector dont support dry-run', $content);
    }
}
