<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\SchemaCreateCommand;
use Patchlevel\EventSourcing\Schema\DryRunSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(SchemaCreateCommand::class)]
final class SchemaCreateCommandTest extends TestCase
{
    public function testSuccessful(): void
    {
        $schemaManager = $this->createMock(SchemaDirector::class);
        $schemaManager->expects($this->atLeastOnce())->method('create');

        $command = new SchemaCreateCommand(
            $schemaManager,
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] schema created', $content);
    }

    public function testDryRun(): void
    {
        $schemaManager = $this->createMock(DryRunSchemaDirector::class);
        $schemaManager->method('dryRunCreate')->willReturn([
            'create table 1;',
            'create table 2;',
            'create table 3;',
        ]);

        $command = new SchemaCreateCommand(
            $schemaManager,
        );

        $input = new ArrayInput(['--dry-run' => true]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('create table 1;', $content);
        self::assertStringContainsString('create table 2;', $content);
        self::assertStringContainsString('create table 3;', $content);
    }

    public function testDryRunNotSupported(): void
    {
        $schemaManager = $this->createMock(SchemaDirector::class);

        $command = new SchemaCreateCommand(
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
