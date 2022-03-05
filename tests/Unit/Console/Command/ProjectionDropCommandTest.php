<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\ProjectionDropCommand;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\Command\ProjectionDropCommand */
final class ProjectionDropCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $repository = $this->prophesize(ProjectionHandler::class);
        $repository->drop(null)->shouldBeCalled();

        $command = new ProjectionDropCommand(
            $repository->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] projection deleted', $content);
    }
}
