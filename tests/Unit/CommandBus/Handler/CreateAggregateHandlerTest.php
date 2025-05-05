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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateAggregateHandler::class)]
final class CreateAggregateHandlerTest extends TestCase
{
    public function testSuccess(): void
    {
        $repository = $this->createMock(Repository::class);
        $repository->expects($this->atLeastOnce())->method('save')->with($this->isInstanceOf(ProfileWithHandler::class));

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager->expects($this->atLeastOnce())->method('get')->with(ProfileWithHandler::class)
            ->willReturn($repository);

        $handler = new CreateAggregateHandler(
            $repositoryManager,
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

        $repository = $this->createMock(Repository::class);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager->expects($this->atLeastOnce())->method('get')->with($class::class)
            ->willReturn($repository);

        $handler = new CreateAggregateHandler(
            $repositoryManager,
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
