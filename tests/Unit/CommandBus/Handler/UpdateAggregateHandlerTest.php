<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus\Handler;

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
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(UpdateAggregateHandler::class)]
final class UpdateAggregateHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccess(): void
    {
        $profileId = ProfileId::fromString('123');
        $profile = ProfileWithHandler::createEmpty();

        $repository = $this->prophesize(Repository::class);
        $repository->load($profileId)->willReturn($profile)->shouldBeCalled();
        $repository->save($profile)->shouldBeCalled();

        $repositoryManager = $this->prophesize(RepositoryManager::class);
        $repositoryManager
            ->get(ProfileWithHandler::class)
            ->willReturn($repository->reveal())
            ->shouldBeCalled();

        $handler = new UpdateAggregateHandler(
            $repositoryManager->reveal(),
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
        $repositoryManager = $this->prophesize(RepositoryManager::class);

        $handler = new UpdateAggregateHandler(
            $repositoryManager->reveal(),
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
}
