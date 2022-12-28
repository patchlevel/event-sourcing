<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\ProjectionDropCommand;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Dummy2Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\DummyProjection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \Patchlevel\EventSourcing\Console\Command\ProjectionDropCommand
 * @covers \Patchlevel\EventSourcing\Console\Command\ProjectionCommand
 */
final class ProjectionDropCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $projectionA = new DummyProjection();
        $projectionB = new Dummy2Projection();
        $repository = new InMemoryProjectorRepository([$projectionA, $projectionB]);

        $command = new ProjectionDropCommand($repository);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        self::assertTrue($projectionA->dropCalled);
        self::assertTrue($projectionB->dropCalled);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] projection deleted', $content);
    }

    public function testSpecificProjection(): void
    {
        $projectionA = new DummyProjection();
        $projectionB = new Dummy2Projection();
        $repository = new InMemoryProjectorRepository([$projectionA, $projectionB]);

        $command = new ProjectionDropCommand($repository);

        $input = new ArrayInput(['--projection' => DummyProjection::class]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertTrue($projectionA->dropCalled);
        self::assertFalse($projectionB->dropCalled);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] projection deleted', $content);
    }
}
