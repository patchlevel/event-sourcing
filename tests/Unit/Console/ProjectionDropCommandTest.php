<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\ProjectionDropCommand;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ProjectionDropCommandTest extends TestCase
{
    use ProphecyTrait;
    use MatchesSnapshots;

    public function testSuccessful(): void
    {
        $repository = $this->prophesize(ProjectionRepository::class);
        $repository->drop()->shouldBeCalled();

        $command = new ProjectionDropCommand(
            $repository->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);
        self::assertMatchesSnapshot($output->fetch());
    }
}
