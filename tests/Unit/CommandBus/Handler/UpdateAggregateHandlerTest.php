<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus\Handler;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\CommandBus\Handler\AggregateIdNotFound;
use Patchlevel\EventSourcing\CommandBus\Handler\DefaultParameterResolver;
use Patchlevel\EventSourcing\CommandBus\Handler\UpdateAggregateHandler;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ChangeProfileName;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateAggregateHandler::class)]
final class UpdateAggregateHandlerTest extends TestCase
{
    public function testSuccess(): void
    {
        $profileId = ProfileId::fromString('123');
        $profile = ProfileWithHandler::createEmpty();

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->atLeastOnce())->method('load')->with($profileId)->willReturn($profile);
        $repository->expects($this->atLeastOnce())->method('save')->with($profile);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager
            ->expects($this->atLeastOnce())
            ->method('get')
            ->with(ProfileWithHandler::class)
            ->willReturn($repository);

        $handler = new UpdateAggregateHandler(
            $repositoryManager,
            ProfileWithHandler::class,
            'changeName',
            new DefaultParameterResolver(),
        );

        $command = new ChangeProfileName(
            ProfileId::fromString('123'),
            'test',
        );

        $handler->__invoke($command);
    }

    public function testMissingAggregateId(): void
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);

        $handler = new UpdateAggregateHandler(
            $repositoryManager,
            ProfileWithHandler::class,
            'changeName',
            new DefaultParameterResolver(),
        );

        $command = new CreateProfile(
            ProfileId::fromString('123'),
            'test',
        );

        $this->expectException(AggregateIdNotFound::class);

        $handler->__invoke($command);
    }

    public function testIdPropertyNotIdentifier(): void
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);

        $handler = new UpdateAggregateHandler(
            $repositoryManager,
            ProfileWithHandler::class,
            'changeName',
            new DefaultParameterResolver(),
        );

        $command = new class ('123') {
            public function __construct(
                #[Id]
                public readonly string $profileId,
            ) {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Id property must be an instance of AggregateRootId');

        $handler->__invoke($command);
    }
}
