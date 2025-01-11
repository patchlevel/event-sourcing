<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus\Handler;

use InvalidArgumentException;
use Patchlevel\EventSourcing\CommandBus\Handler\CreateAggregateHandler;
use Patchlevel\EventSourcing\CommandBus\Handler\DefaultParameterResolver;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\CommandBus\Handler\CreateAggregateHandler */
final class CreateAggregateHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccess(): void
    {
        $repository = $this->prophesize(Repository::class);
        $repository->save(Argument::type(ProfileWithHandler::class))->shouldBeCalled();

        $repositoryManager = $this->prophesize(RepositoryManager::class);
        $repositoryManager
            ->get(ProfileWithHandler::class)
            ->willReturn($repository->reveal())
            ->shouldBeCalled();

        $handler = new CreateAggregateHandler(
            $repositoryManager->reveal(),
            ProfileWithHandler::class,
            'create',
            new DefaultParameterResolver(),
        );

        $command = new CreateProfile(
            ProfileId::fromString('123'),
            'test',
        );

        $handler->__invoke($command);
    }

    public function testNoAggregate(): void
    {
        $class = new class () {
            public static function create(): string
            {
                return 'test';
            }
        };

        $repository = $this->prophesize(Repository::class);

        $repositoryManager = $this->prophesize(RepositoryManager::class);
        $repositoryManager
            ->get($class::class)
            ->willReturn($repository->reveal())
            ->shouldBeCalled();

        $handler = new CreateAggregateHandler(
            $repositoryManager->reveal(),
            $class::class,
            'create',
            new DefaultParameterResolver(),
        );

        $command = new CreateProfile(
            ProfileId::fromString('123'),
            'test',
        );

        $this->expectException(InvalidArgumentException::class);

        $handler->__invoke($command);
    }
}
