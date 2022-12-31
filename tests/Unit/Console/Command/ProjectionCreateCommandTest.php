<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\ProjectionCreateCommand;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Dummy2Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\DummyProjection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \Patchlevel\EventSourcing\Console\Command\ProjectionCreateCommand
 * @covers \Patchlevel\EventSourcing\Console\Command\ProjectionCommand
 */
final class ProjectionCreateCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $projectionA = new DummyProjection();
        $projectionB = new Dummy2Projection();
        $repository = new InMemoryProjectorRepository([$projectionA, $projectionB]);

        $command = new ProjectionCreateCommand($repository);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        self::assertTrue($projectionA->createCalled);
        self::assertTrue($projectionB->createCalled);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] projection created', $content);
    }

    public function testSpecificProjection(): void
    {
        $projectionA = new DummyProjection();
        $projectionB = new Dummy2Projection();
        $repository = new InMemoryProjectorRepository([$projectionA, $projectionB]);

        $command = new ProjectionCreateCommand($repository);

        $input = new ArrayInput(['--projection' => DummyProjection::class]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertTrue($projectionA->createCalled);
        self::assertFalse($projectionB->createCalled);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] projection created', $content);
    }
}
