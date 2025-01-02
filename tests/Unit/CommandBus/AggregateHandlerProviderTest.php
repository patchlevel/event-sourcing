<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\Handler\CreateAggregateHandler;
use Patchlevel\EventSourcing\CommandBus\Handler\DefaultParameterResolver;
use Patchlevel\EventSourcing\CommandBus\Handler\UpdateAggregateHandler;
use Patchlevel\EventSourcing\CommandBus\InvalidHandleMethod;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ActivateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ChangeProfileName;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithHandler;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithNoParameterHandler;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithNoTypeHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider */
final class AggregateHandlerProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testNoParameters(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithNoParameterHandler::class]),
            $repositoryManager->reveal(),
        );

        $this->expectException(InvalidHandleMethod::class);

        $provider->handlerForCommand(ChangeProfileName::class);
    }

    public function testNoType(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithNoTypeHandler::class]),
            $repositoryManager->reveal(),
        );

        $this->expectException(InvalidHandleMethod::class);

        $provider->handlerForCommand(ChangeProfileName::class);
    }

    public function testEmpty(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry([]),
            $repositoryManager->reveal(),
        );

        $result = $provider->handlerForCommand(CreateProfile::class);

        self::assertCount(0, $result);
    }

    public function testGetCreateHandler(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithHandler::class]),
            $repositoryManager->reveal(),
        );

        $result = $provider->handlerForCommand(CreateProfile::class);

        $handler = new CreateAggregateHandler(
            $repositoryManager->reveal(),
            ProfileWithHandler::class,
            'create',
            new DefaultParameterResolver(),
        );

        self::assertCount(1, $result);
        self::assertEquals($handler->__invoke(...), $result[0]->callable());
    }

    public function testGetUpdateHandler(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithHandler::class]),
            $repositoryManager->reveal(),
        );

        $result = $provider->handlerForCommand(ChangeProfileName::class);

        $handler = new UpdateAggregateHandler(
            $repositoryManager->reveal(),
            ProfileWithHandler::class,
            'updateName',
            new DefaultParameterResolver(),
        );

        self::assertCount(1, $result);
        self::assertEquals($handler->__invoke(...), $result[0]->callable());
    }

    public function testUpdateHandlerWithoutParameter(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithHandler::class]),
            $repositoryManager->reveal(),
        );

        $result = $provider->handlerForCommand(ActivateProfile::class);

        $handler = new UpdateAggregateHandler(
            $repositoryManager->reveal(),
            ProfileWithHandler::class,
            'activate',
            new DefaultParameterResolver(),
        );

        self::assertCount(1, $result);
        self::assertEquals($handler->__invoke(...), $result[0]->callable());
    }
}
